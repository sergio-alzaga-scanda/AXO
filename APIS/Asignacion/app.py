from flask import Flask, jsonify
import requests
import pymysql
import json
from db import get_connection
from datetime import datetime, time, timedelta
import warnings
import re
import math
import openai
import traceback
import os

app = Flask(__name__)

BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/"
API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401"

# ===================================================================
# CONFIGURACIÓN GPT-4o
# ===================================================================
GPT_CONFIG = {
    "endpoint": "https://yuscanopenai.openai.azure.com/openai/deployments/gpt-4o/chat/completions?api-version=2025-01-01-preview",
    "api_key": "b5cf6623705b45e1befed28fda1350f7",
    "model_name": "gpt-4o",
    "deployment": "gpt-4o",
    "version": "2024-12-01-preview"
}

# Configurar cliente OpenAI para Azure
client = openai.AzureOpenAI(
    api_key=GPT_CONFIG["api_key"],
    api_version=GPT_CONFIG["version"],
    azure_endpoint="https://yuscanopenai.openai.azure.com/"
)

# ===================================================================
# INICIALIZACIÓN DE BASE DE DATOS
# ===================================================================
def inicializar_tablas_si_no_existen():
    """Crea todas las tablas necesarias si no existen"""
    tablas = [
        {
            "nombre": "log_estado_tecnicos",
            "sql": """
                CREATE TABLE IF NOT EXISTS log_estado_tecnicos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_tecnico INT NOT NULL,
                    estado_asistencia TINYINT(1) DEFAULT 0,
                    razon_cambio VARCHAR(255),
                    fecha_cambio DATETIME,
                    INDEX idx_tecnico (id_tecnico),
                    INDEX idx_fecha (fecha_cambio)
                )
            """
        },
        {
            "nombre": "tickets_asignados",
            "sql": """
                CREATE TABLE IF NOT EXISTS tickets_asignados (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_ticket VARCHAR(50) NOT NULL,
                    usuario_tecnico VARCHAR(100) NOT NULL,
                    grupo VARCHAR(255),
                    templete VARCHAR(255),
                    fecha_asignacion DATETIME,
                    respuesta_api TEXT,
                    descripcion_original TEXT,
                    descripcion_limpia TEXT,
                    tiempo_procesamiento FLOAT,
                    palabras_clave TEXT,
                    confianza INT,
                    asunto_ticket VARCHAR(500),
                    analisis_gpt TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_ticket (id_ticket),
                    INDEX idx_fecha (fecha_asignacion)
                )
            """
        },
        {
            "nombre": "analisis_gpt_log",
            "sql": """
                CREATE TABLE IF NOT EXISTS analisis_gpt_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id VARCHAR(50),
                    plantilla_id INT,
                    analisis_json TEXT,
                    fecha_analisis DATETIME,
                    asunto_ticket VARCHAR(500),
                    INDEX idx_ticket (ticket_id),
                    INDEX idx_fecha (fecha_analisis)
                )
            """
        },
        {
            "nombre": "control_asignacion",
            "sql": """
                CREATE TABLE IF NOT EXISTS control_asignacion (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_ticket VARCHAR(50) DEFAULT NULL,
                    id_tecnico INT NOT NULL,
                    ultimo_id_asignado INT DEFAULT NULL,
                    total_disponibles INT NOT NULL,
                    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    metodo ENUM('carrusel', 'manual', 'especifico') DEFAULT 'carrusel'
                )
            """
        },
        {
            "nombre": "configuracion_asignacion",
            "sql": """
                CREATE TABLE IF NOT EXISTS configuracion_asignacion (
                    id INT PRIMARY KEY,
                    valor VARCHAR(255),
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            """
        }
    ]
    
    try:
        conexion = get_connection()
        cursor = conexion.cursor()
        
        print("🔧 INICIALIZANDO TABLAS DE BASE DE DATOS...")
        
        for tabla in tablas:
            try:
                cursor.execute(tabla["sql"])
                print(f"   ✅ Tabla '{tabla['nombre']}' verificada/creada")
            except Exception as e:
                print(f"   ⚠️ Error con tabla '{tabla['nombre']}': {str(e)}")
        
        conexion.commit()
        conexion.close()
        print("✅ Tablas inicializadas correctamente\n")
        return True
        
    except Exception as e:
        print(f"❌ Error crítico al inicializar tablas: {str(e)}")
        import traceback
        traceback.print_exc()
        return False

# Inicializar tablas al iniciar la aplicación
inicializar_tablas_si_no_existen()

# ===================================================================
# FUNCIONES AUXILIARES MEJORADAS
# ===================================================================
def obtener_info_tiempo_actual():
    """Obtiene la hora actual y el día de la semana actual en inglés corto"""
    ahora = datetime.now()
    hora_actual = ahora.time()
    
    mapeo_dias = {
        0: "Mon", 1: "Tue", 2: "Wed", 3: "Thu", 
        4: "Fri", 5: "Sat", 6: "Sun"
    }
    
    dia_actual = mapeo_dias[ahora.weekday()]
    return hora_actual, dia_actual


def convertir_a_tiempo(objeto_tiempo):
    """Convierte diferentes tipos de objetos a datetime.time"""
    if isinstance(objeto_tiempo, time):
        return objeto_tiempo
    elif isinstance(objeto_tiempo, timedelta):
        segundos = objeto_tiempo.total_seconds()
        horas = int(segundos // 3600)
        minutos = int((segundos % 3600) // 60)
        segundos = int(segundos % 60)
        return time(horas, minutos, segundos)
    elif isinstance(objeto_tiempo, str):
        try:
            return datetime.strptime(objeto_tiempo, '%H:%M:%S').time()
        except:
            try:
                return datetime.strptime(objeto_tiempo, '%H:%M').time()
            except:
                return time(0, 0, 0)
    else:
        return time(0, 0, 0)


def esta_dentro_rango_horario(hora_verificar, hora_inicio, hora_fin):
    """Verifica si una hora está dentro de un rango"""
    inicio = convertir_a_tiempo(hora_inicio)
    fin = convertir_a_tiempo(hora_fin)
    verificar = convertir_a_tiempo(hora_verificar)
    
    if inicio <= fin:
        return inicio <= verificar <= fin
    else:
        return verificar >= inicio or verificar <= fin


def restar_minutos(objeto_tiempo, minutos):
    """Resta minutos a un objeto time"""
    tiempo_convertido = convertir_a_tiempo(objeto_tiempo)
    fecha_temporal = datetime.combine(datetime.today(), tiempo_convertido)
    nueva_fecha = fecha_temporal - timedelta(minutes=minutos)
    return nueva_fecha.time()


def extraer_texto_de_html(contenido_html):
    """Extrae texto limpio de contenido HTML"""
    if not contenido_html:
        return ""
    
    texto = re.sub(r'<[^>]+>', ' ', contenido_html)
    texto = re.sub(r'\s+', ' ', texto)
    texto = re.sub(r'Aviso de Confidencialidad.*?Confidentiality Notice.*', '', texto, flags=re.DOTALL)
    texto = re.sub(r'Confidentiality Notice.*', '', texto, flags=re.DOTALL)
    
    return texto.strip()


def extraer_palabras_clave_asunto(asunto):
    """Extrae palabras clave relevantes del asunto."""
    asunto_limpio = asunto.lower().strip()
    
    palabras_ignorar = ["el", "la", "los", "las", "de", "del", "y", "o", "u", "a", "ante", "bajo", 
                        "con", "contra", "desde", "en", "entre", "hacia", "hasta", "para", "por", 
                        "según", "sin", "so", "sobre", "tras", "que", "se", "un", "una", "unos", 
                        "unas", "al", "es", "son", "fue", "fueron", "ha", "han", "he", "hemos", 
                        "problema", "error", "ayuda", "soporte", "ticket", "incidente", "requerimiento",
                        "prueba", "test", "hola", "buenos", "dias", "tardes", "noches", "saludos",
                        "por favor", "favor", "gracias", "urgente", "importante", "necesito"]
    
    palabras = re.findall(r'\b\w+\b', asunto_limpio)
    
    palabras_clave = []
    for palabra in palabras:
        if (len(palabra) > 2 and
            palabra not in palabras_ignorar and
            not palabra.isdigit() and
            palabra not in palabras_clave):
            palabras_clave.append(palabra)
    
    return palabras_clave


def es_asunto_generico(asunto):
    """Determina si un asunto es genérico"""
    asunto_normalizado = asunto.lower().strip()
    
    palabras_genericas = [
        "prueba", "test", "hola", "ayuda", "error", "problema", 
        "soporte", "ticket", "incidente", "requerimiento", 
        "consult", "duda", "pregunta", "informacion", "info",
        "por favor", "favor", "gracias", "urgente", "importante",
        "necesito", "solicito", "solicitud", "servicio", "asistencia",
        "auxilio", "falla", "averia", "daño", "roto", "no funciona",
        "no sirve", "issue", "help", "support", "request", "ticket"
    ]
    
    for palabra in palabras_genericas:
        if palabra in asunto_normalizado:
            return True
    
    if len(asunto_normalizado.split()) <= 2:
        return True
    
    return False


# ===================================================================
# ACTUALIZACIÓN DE ESTADO DE TÉCNICOS SEGÚN HORARIO
# ===================================================================
def actualizar_estado_tecnicos_por_horario():
    """
    Actualiza el campo 'activo' de los técnicos según su horario actual.
    """
    try:
        try:
            conexion = get_connection()
            cursor = conexion.cursor()
        except:
             return False
        
        hora_actual, dia_actual = obtener_info_tiempo_actual()
        print(f"\n📅 ACTUALIZANDO ESTADO DE TÉCNICOS POR HORARIO (Campo: ACTIVO)")
        print(f"   Día: {dia_actual}, Hora: {hora_actual}")
        
        # Verificar que la tabla tecnicos existe
        cursor.execute("SHOW TABLES LIKE 'tecnicos'")
        if not cursor.fetchone():
            print("❌ ERROR: La tabla 'tecnicos' no existe")
            conexion.close()
            return False
        
        # Obtener TODOS los técnicos
        cursor.execute("SELECT id FROM tecnicos")
        tecnicos_activos = cursor.fetchall()
        
        if not tecnicos_activos:
            print("ℹ️ No hay técnicos en el sistema")
            conexion.close()
            return True
        
        actualizados = 0
        for (id_tecnico,) in tecnicos_activos:
            # Obtener horario del técnico para el día actual
            cursor_horario = conexion.cursor(pymysql.cursors.DictCursor)
            cursor_horario.execute("""
                SELECT hora_entrada, hora_salida, inicio_comida, fin_comida 
                FROM horarios_tecnicos 
                WHERE id_tecnico = %s AND dia_semana = %s
            """, (id_tecnico, dia_actual))
            
            horario = cursor_horario.fetchone()
            cursor_horario.close()
            
            nuevo_estado = 0
            razon = ""

            if not horario:
                nuevo_estado = 0
                razon = "No tiene horario definido para hoy"
            else:
                hora_entrada = convertir_a_tiempo(horario['hora_entrada'])
                hora_salida = convertir_a_tiempo(horario['hora_salida'])
                inicio_comida = convertir_a_tiempo(horario['inicio_comida'])
                fin_comida = convertir_a_tiempo(horario['fin_comida'])
                
                # Verificar si está fuera de horario laboral
                if not esta_dentro_rango_horario(hora_actual, hora_entrada, hora_salida):
                    nuevo_estado = 0
                    razon = "Fuera de horario laboral"
                
                # Verificar si está 20 minutos antes de comida
                elif inicio_comida and inicio_comida != hora_entrada:
                    buffer_comida = restar_minutos(inicio_comida, 20)
                    if esta_dentro_rango_horario(hora_actual, buffer_comida, inicio_comida):
                        nuevo_estado = 0
                        razon = "20 minutos antes de comida"
                    
                    # Verificar si está en horario de comida
                    elif fin_comida and esta_dentro_rango_horario(hora_actual, inicio_comida, fin_comida):
                        nuevo_estado = 0
                        razon = "En horario de comida"
                    else:
                        # Verificar si está 20 minutos antes de salida
                        if hora_salida != hora_entrada:
                            buffer_salida = restar_minutos(hora_salida, 20)
                            if esta_dentro_rango_horario(hora_actual, buffer_salida, hora_salida):
                                nuevo_estado = 0
                                razon = "20 minutos antes de salida"
                            else:
                                nuevo_estado = 1
                                razon = "Disponible según horario"
                        else:
                            nuevo_estado = 1
                            razon = "Disponible según horario"
                else:
                     # Verificar si está 20 minutos antes de salida
                    if hora_salida != hora_entrada:
                        buffer_salida = restar_minutos(hora_salida, 20)
                        if esta_dentro_rango_horario(hora_actual, buffer_salida, hora_salida):
                            nuevo_estado = 0
                            razon = "20 minutos antes de salida"
                        else:
                            nuevo_estado = 1
                            razon = "Disponible según horario"
                    else:
                        nuevo_estado = 1
                        razon = "Disponible según horario"
            
            # Actualizar el estado del técnico
            try:
                cursor.execute("""
                    UPDATE tecnicos 
                    SET activo = %s 
                    WHERE id = %s
                """, (nuevo_estado, id_tecnico))
                
                # Registrar log
                try:
                    cursor.execute("""
                        INSERT INTO log_estado_tecnicos 
                        (id_tecnico, estado_asistencia, razon_cambio, fecha_cambio) 
                        VALUES (%s, %s, %s, %s)
                    """, (id_tecnico, nuevo_estado, razon, datetime.now()))
                except Exception as log_error:
                    pass
                
                actualizados += 1
                # print(f"   Técnico ID {id_tecnico}: {razon} → activo={nuevo_estado}")
                
            except Exception as update_error:
                print(f"   ❌ Error al actualizar técnico {id_tecnico}: {str(update_error)}")
        
        conexion.commit()
        conexion.close()
        
        print(f"✅ Técnicos evaluados y actualizados: {actualizados}")
        return True
        
    except Exception as e:
        print(f"❌ Error al actualizar estado de técnicos: {str(e)}")
        return False


# ===================================================================
# ANÁLISIS CON GPT-4o
# ===================================================================
def analizar_con_gpt(asunto_ticket, descripcion_ticket, plantillas_disponibles):
    """
    Analiza el ticket usando GPT-4o para encontrar la plantilla más adecuada.
    """
    try:
        asunto_es_generico = es_asunto_generico(asunto_ticket)
        
        plantillas_info = []
        for i, plantilla in enumerate(plantillas_disponibles):
            nombre = plantilla.get("plantilla_incidente", "Sin nombre")
            descripcion = plantilla.get("descripcion", "")
            grupo = plantilla.get("grupo", "")
            origen = plantilla.get("origen", "")
            palabras_clave_db = plantilla.get("palabras_clave", "")
            
            palabras_clave_lista = []
            if palabras_clave_db:
                palabras_clave_lista = [p.strip().lower() for p in palabras_clave_db.split(',') if p.strip()]
            
            plantilla_info = f"PLANTILLA {i+1}: {nombre}\n"
            plantilla_info += f"Descripción: {descripcion[:150]}\n"
            if palabras_clave_lista:
                plantilla_info += f"Palabras clave: {', '.join(palabras_clave_lista)}\n"
            if grupo:
                plantilla_info += f"Grupo destino: {grupo}\n"
            if origen:
                plantilla_info += f"Origen específico: {origen}\n"
            plantilla_info += "-" * 40
            
            plantillas_info.append(plantilla_info)
        
        plantillas_texto = "\n".join(plantillas_info)
        
        if asunto_es_generico:
            system_prompt = """Eres un experto en clasificación de tickets de soporte técnico.
            
            IMPORTANTE: El ASUNTO es genérico.
            Da MÁS PESO a la DESCRIPCIÓN para encontrar la plantilla correcta.
            Solo tomaras los que sean referentes a bloqueo de cuenta, restet de cuenta y/o cambio de contraseña o algun otro sobre ese mismo contexto
            y verifica que vayan en ese contexto 
            Responde ÚNICAMENTE con un JSON válido:
            {
                "plantilla_seleccionada": "nombre_de_la_plantilla" o null,
                "indice_plantilla": numero_indice o null,
                "confianza": 0-100,
                "razon_principal": "Explicación detallada que justifique la decisión",
                "asunto_generico": true,
                "coincidencias_descripcion": ["palabra1", "palabra2", ...],
                "grupo_recomendado": "nombre_del_grupo" o null
            }"""
        else:
            system_prompt = """Eres un experto en clasificación de tickets de soporte técnico.
            Da PRIORIDAD al ASUNTO (70% peso), pero también considera la descripción (30% peso).
            
            Responde ÚNICAMENTE con un JSON válido:
            {
                "plantilla_seleccionada": "nombre_de_la_plantilla" o null,
                "indice_plantilla": numero_indice o null,
                "confianza": 0-100,
                "razon_principal": "Explicación detallada",
                "asunto_generico": false,
                "coincidencias_asunto": ["palabra1", "palabra2", ...],
                "coincidencias_descripcion": ["palabra1", "palabra2", ...],
                "grupo_recomendado": "nombre_del_grupo" o null
            }"""
        
        user_prompt = f"""TICKET A ANALIZAR:

📌 **ASUNTO:** "{asunto_ticket}"
{"⚠️ **NOTA:** Asunto genérico detectado - dar MÁS PESO a la DESCRIPCIÓN" if asunto_es_generico else ""}

📝 **DESCRIPCIÓN:**
{descripcion_ticket[:1500] if descripcion_ticket else "Sin descripción"}

🔍 **PLANTILLAS DISPONIBLES:**
{plantillas_texto}

📊 **INSTRUCCIONES DE ANÁLISIS:**
{"1. El ASUNTO es GENÉRICO, así que ANALIZA PRINCIPALMENTE LA DESCRIPCIÓN (90% importancia)" if asunto_es_generico else "1. Analiza el ASUNTO primero (70% importancia), luego la descripción (30%)"}
2. Busca coincidencias ESPECÍFICAS entre el ticket y las plantillas
3. Si no hay suficiente evidencia, NO selecciones ninguna plantilla
4. Sé exigente: no forces relaciones que no existen claramente

¿Qué plantilla es la más adecuada? Responde con el JSON solicitado."""

        response = client.chat.completions.create(
            model=GPT_CONFIG["deployment"],
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            temperature=0.1,
            max_tokens=1200,
            response_format={"type": "json_object"}
        )
        
        respuesta_gpt = json.loads(response.choices[0].message.content)
        
        plantilla_seleccionada = None
        if respuesta_gpt.get("plantilla_seleccionada") and respuesta_gpt.get("indice_plantilla") is not None:
            indice = respuesta_gpt["indice_plantilla"] - 1
            if 0 <= indice < len(plantillas_disponibles):
                plantilla_seleccionada = plantillas_disponibles[indice]
        
        return {
            "plantilla": plantilla_seleccionada,
            "analisis_gpt": respuesta_gpt,
            "raw_response": response.choices[0].message.content
        }
        
    except Exception as e:
        print(f"Error en análisis GPT: {str(e)}")
        return {
            "plantilla": None,
            "analisis_gpt": {
                "error": str(e),
                "confianza": 0,
                "razon": f"Error en el análisis con GPT: {str(e)}"
            },
            "raw_response": None
        }


def analizar_similitud_texto_con_gpt(asunto_ticket, descripcion_ticket, texto_plantilla):
    """
    Análisis de similitud mejorado que detecta asuntos genéricos.
    """
    try:
        asunto_es_generico = es_asunto_generico(asunto_ticket)
        
        if asunto_es_generico:
            prompt = f"""Analiza la similitud entre el ticket y la plantilla.

🔥 **IMPORTANTE:** El ASUNTO es GENÉRICO ("{asunto_ticket}"), da 90% de peso a la DESCRIPCIÓN.

📋 **TICKET:**
ASUNTO (genérico, 10% peso): "{asunto_ticket}"
DESCRIPCIÓN (90% peso): {descripcion_ticket[:800] if descripcion_ticket else "Sin descripción"}

📄 **PLANTILLA:**
{texto_plantilla[:800]}

Responde en formato JSON:
{{
    "asunto_generico": true,
    "similitud_asunto": 0-100,
    "similitud_descripcion": 0-100,
    "similitud_total": 0-100,
    "coincidencias_descripcion": ["palabra1", "palabra2", ...],
    "contexto_compartido": true/false,
    "explicacion": "explicación detallada"
}}"""
        else:
            prompt = f"""Analiza la similitud entre el ticket y la plantilla.

📋 **TICKET:**
ASUNTO (70% peso): "{asunto_ticket}"
DESCRIPCIÓN (30% peso): {descripcion_ticket[:800] if descripcion_ticket else "Sin descripción"}

📄 **PLANTILLA:**
{texto_plantilla[:800]}

Responde en formato JSON:
{{
    "asunto_generico": false,
    "similitud_asunto": 0-100,
    "similitud_descripcion": 0-100,
    "similitud_total": 0-100,
    "coincidencias_asunto": ["palabra1", "palabra2", ...],
    "coincidencias_descripcion": ["palabra1", "palabra2", ...],
    "contexto_compartido": true/false,
    "explicacion": "explicación detallada"
}}"""

        response = client.chat.completions.create(
            model=GPT_CONFIG["deployment"],
            messages=[
                {"role": "system", "content": "Eres un analista de texto especializado en evaluación de similitud. Sé objetivo y riguroso."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.1,
            max_tokens=800,
            response_format={"type": "json_object"}
        )
        
        resultado = json.loads(response.choices[0].message.content)
        
        if "similitud_total" not in resultado:
            if resultado.get("asunto_generico"):
                sim_asunto = resultado.get("similitud_asunto", 0)
                sim_desc = resultado.get("similitud_descripcion", 0)
                resultado["similitud_total"] = int((sim_asunto * 0.1) + (sim_desc * 0.9))
            else:
                sim_asunto = resultado.get("similitud_asunto", 0)
                sim_desc = resultado.get("similitud_descripcion", 0)
                resultado["similitud_total"] = int((sim_asunto * 0.7) + (sim_desc * 0.3))
        
        return resultado
        
    except Exception as e:
        print(f"Error en análisis de similitud GPT: {str(e)}")
        return {
            "asunto_generico": es_asunto_generico(asunto_ticket),
            "similitud_asunto": 0,
            "similitud_descripcion": 0,
            "similitud_total": 0,
            "contexto_compartido": False,
            "explicacion": f"Error en análisis: {str(e)}"
        }


# ===================================================================
# COINCIDENCIA DE PLANTILLAS
# ===================================================================
def coincidir_plantilla_con_gpt(origen, articulo, descripcion_ticket=""):
    """
    Coincidencia de plantillas usando GPT-4o.
    """
    plantillas = cargar_plantillas()
    
    if not plantillas:
        print("INFORMACIÓN: No hay plantillas activas en la base de datos")
        return None
    
    origen_normalizado = origen.lower().strip() if origen else ""
    articulo_normalizado = articulo.lower().strip()
    
    print(f"\n🔍 ANALIZANDO TICKET CON GPT-4o:")
    print(f"   📌 ASUNTO: {articulo}")
    print(f"   📝 Descripción: {descripcion_ticket[:150]}..." if descripcion_ticket else "   📝 Sin descripción")
    print(f"   👤 Origen: {origen}")
    
    asunto_es_generico = es_asunto_generico(articulo)
    
    if asunto_es_generico:
        print(f"   ⚠️ ASUNTO GENÉRICO DETECTADO: '{articulo}'")
        print(f"   📊 Se dará MÁS PESO a la DESCRIPCIÓN para el análisis (90% descripción, 10% asunto)")
    else:
        print(f"   ✅ Asunto ESPECÍFICO detectado")
        print(f"   📊 Peso normal: 70% asunto, 30% descripción")
    
    plantillas_filtradas = []
    for plantilla in plantillas:
        origen_plantilla = (plantilla.get("origen") or "").lower().strip()
        if not origen_plantilla or origen_normalizado == origen_plantilla:
            plantillas_filtradas.append(plantilla)
    
    if not plantillas_filtradas:
        print("   ⚠️ No hay plantillas que coincidan con el origen del ticket")
        return None
    
    print(f"\n📊 PLANTILLAS DISPONIBLES PARA ANÁLISIS GPT: {len(plantillas_filtradas)}")
    
    resultado_gpt = analizar_con_gpt(articulo, descripcion_ticket, plantillas_filtradas)
    
    if resultado_gpt["plantilla"]:
        plantilla_seleccionada = resultado_gpt["plantilla"]
        analisis = resultado_gpt["analisis_gpt"]
        confianza = analisis.get("confianza", 0)
        
        print(f"\n{'='*80}")
        print(f"🤖 GPT-4o HA SELECCIONADO UNA PLANTILLA:")
        print(f"   📋 Plantilla: {plantilla_seleccionada.get('plantilla_incidente')}")
        print(f"   📈 Confianza: {confianza}%")
        
        if "razon_principal" in analisis:
            razon = analisis.get("razon_principal", "")
            print(f"   💡 Razón: {razon[:250]}...")
        
        if analisis.get("asunto_generico"):
            print(f"   ⚠️ Asunto considerado GENÉRICO - Análisis basado principalmente en descripción")
        
        if asunto_es_generico:
            umbral_confianza = 60
            umbral_similitud = 50
            print(f"   📊 Umbrales ajustados para asunto genérico:")
            print(f"     - Confianza mínima requerida: {umbral_confianza}%")
            print(f"     - Similitud mínima requerida: {umbral_similitud}%")
        else:
            umbral_confianza = 40
            umbral_similitud = 30
        
        if confianza >= umbral_confianza:
            texto_plantilla = plantilla_seleccionada.get("descripcion", "") or plantilla_seleccionada.get("plantilla_incidente", "")
            validacion = analizar_similitud_texto_con_gpt(articulo, descripcion_ticket, texto_plantilla)
            
            similitud_total = validacion.get("similitud_total", 0)
            
            if similitud_total >= umbral_similitud:
                print(f"\n   ✅ VALIDACIÓN EXITOSA:")
                print(f"     Similitud total: {similitud_total}%")
                print(f"     Contexto compartido: {'Sí' if validacion.get('contexto_compartido') else 'No'}")
                
                if "explicacion" in validacion:
                    print(f"     Explicación: {validacion['explicacion'][:150]}...")
                
                guardar_analisis_gpt(
                    ticket_id=None,
                    plantilla_id=plantilla_seleccionada.get("id"),
                    analisis_completo=resultado_gpt,
                    asunto_ticket=articulo
                )
                
                return plantilla_seleccionada
            else:
                print(f"\n   ❌ VALIDACIÓN FALLIDA:")
                print(f"     Similitud total: {similitud_total}% < {umbral_similitud}% (requerido)")
                print(f"     Razón: Similitud insuficiente según validación GPT")
        else:
            print(f"\n   ⚠️ CONFIABILIDAD INSUFICIENTE: {confianza}% < {umbral_confianza}% (requerido)")
    else:
        print(f"\n❌ GPT-4o NO ENCONTRÓ PLANTILLA ADECUADA")
        if "razon_principal" in resultado_gpt["analisis_gpt"]:
            razon = resultado_gpt["analisis_gpt"]["razon_principal"]
            print(f"   📝 Razón: {razon[:200]}...")
    
    if asunto_es_generico and descripcion_ticket:
        print(f"\n🔍 BÚSQUEDA DIRECTA EN DESCRIPCIÓN (fallback para asunto genérico):")
        
        descripcion_normalizada = descripcion_ticket.lower()
        coincidencias_descripcion = []
        
        for plantilla in plantillas_filtradas:
            palabras_clave_db = plantilla.get("palabras_clave", "")
            if palabras_clave_db:
                palabras_clave = [p.strip().lower() for p in palabras_clave_db.split(',') if p.strip()]
                for palabra in palabras_clave:
                    if palabra and len(palabra) > 3 and palabra in descripcion_normalizada:
                        coincidencias_descripcion.append({
                            "plantilla": plantilla,
                            "palabra_clave": palabra,
                            "tipo": "coincidencia_en_descripcion"
                        })
                        break
        
        if coincidencias_descripcion:
            print(f"   ✅ Coincidencias encontradas en descripción:")
            for coinc in coincidencias_descripcion[:3]:
                nombre = coinc["plantilla"].get("plantilla_incidente", "Sin nombre")
                palabra = coinc["palabra_clave"]
                print(f"     • {nombre} → palabra clave: '{palabra}'")
            
            plantilla_fallback = coincidencias_descripcion[0]["plantilla"]
            print(f"\n🔄 USANDO COINCIDENCIA EN DESCRIPCIÓN COMO FALLBACK:")
            print(f"   ✅ Seleccionada: {plantilla_fallback.get('plantilla_incidente')}")
            print(f"   🔑 Palabra clave encontrada: '{coincidencias_descripcion[0]['palabra_clave']}'")
            
            analisis_simulado = {
                "plantilla_seleccionada": plantilla_fallback.get("plantilla_incidente"),
                "confianza": 75,
                "razon_principal": f"Coincidencia directa en descripción con palabra clave '{coincidencias_descripcion[0]['palabra_clave']}'",
                "asunto_generico": True
            }
            
            guardar_analisis_gpt(
                ticket_id=None,
                plantilla_id=plantilla_fallback.get("id"),
                analisis_completo={"analisis_gpt": analisis_simulado},
                asunto_ticket=articulo
            )
            
            return plantilla_fallback
        else:
            print(f"   ❌ No se encontraron coincidencias directas en la descripción")
    
    print(f"\n⚠️ NO SE ENCONTRÓ NINGUNA PLANTILLA ADECUADA")
    return None


# ===================================================================
# FUNCIONES DE BASE DE DATOS MEJORADAS
# ===================================================================
def guardar_analisis_gpt(ticket_id, plantilla_id, analisis_completo, asunto_ticket=None):
    """
    Guarda el análisis de GPT en la base de datos para auditoría.
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor()
        
        if asunto_ticket and "analisis_gpt" in analisis_completo:
            analisis_completo["analisis_gpt"]["asunto_analizado"] = asunto_ticket
        
        # Convertir a texto JSON
        analisis_text = json.dumps(analisis_completo, ensure_ascii=False)
        
        sql = """
            INSERT INTO analisis_gpt_log (
                ticket_id, plantilla_id, analisis_json, fecha_analisis
            ) VALUES (%s, %s, %s, %s)
        """
        
        cursor.execute(sql, (
            ticket_id,
            plantilla_id,
            analisis_text[:65535],  # Limitar tamaño para TEXT
            datetime.now()
        ))
        
        conexion.commit()
        conexion.close()
        
        # print(f"📊 Análisis GPT guardado en BD para ticket {ticket_id}")
        return True
        
    except Exception as e:
        print(f"❌ Error al guardar análisis GPT: {str(e)}")
        return False


# ===================================================================
# REQUEST GENÉRICO (GET y PUT) CON LOG
# ===================================================================
def servicedesk_get(endpoint):
    url = BASE_URL + endpoint
    print("\n====== GET ======")
    print("URL:", url)

    warnings.filterwarnings("ignore", message="Unverified HTTPS request")
    respuesta = requests.get(
        url,
        headers={
            "TECHNICIAN_KEY": API_KEY,
            "Content-Type": "application/json"
        },
        verify=False,
        timeout=30
    )

    print("Respuesta:", respuesta.text[:500] if respuesta.text else "Vacía")
    print("=================\n")

    return respuesta.json()


def servicedesk_put(endpoint, payload_json):
    url = BASE_URL + endpoint

    print("\n====== PUT ======")
    print("URL:", url)
    print("PAYLOAD ENVIADO:")
    print(payload_json)
    print("=================")

    warnings.filterwarnings("ignore", message="Unverified HTTPS request")
    respuesta = requests.put(
        url,
        headers={
            "TECHNICIAN_KEY": API_KEY,
            "Content-Type": "application/json"
        },
        data=payload_json,
        verify=False,
        timeout=30
    )

    print("\nRespuesta del servidor externo:")
    print(respuesta.text[:500] if respuesta.text else "Vacía")
    print("=====================\n")

    return respuesta


# ===================================================================
# LECTURA DE TABLAS Y GESTIÓN DE TÉCNICOS (CORREGIDO)
# ===================================================================
def cargar_plantillas():
    """Carga solo plantillas con status=1"""
    try:
        conexion = get_connection()
        cursor = conexion.cursor(pymysql.cursors.DictCursor)
        cursor.execute("SELECT * FROM plantillas_incidentes WHERE status = 1")
        filas = cursor.fetchall()
        conexion.close()
        return filas
    except Exception as e:
        print(f"Error al cargar plantillas: {str(e)}")
        return []


def cargar_tecnicos_activos():
    """
    Trae solo técnicos activos (activo=1) ordenados por orden_asignacion e id.
    Esto es CRUCIAL para que el carrusel sea consistente.
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor(pymysql.cursors.DictCursor)
        cursor.execute("""
            SELECT * FROM tecnicos 
            WHERE activo = 1 
            ORDER BY orden_asignacion ASC, id ASC
        """)
        filas = cursor.fetchall()
        conexion.close()
        return filas
    except Exception as e:
        print(f"Error al cargar técnicos activos: {str(e)}")
        return []


def obtener_tecnicos_disponibles():
    """
    Obtiene la lista de técnicos disponibles actualmente.
    Simplificado para usar la lógica centralizada de 'activo'.
    """
    # 1. ACTUALIZAR ESTADO DE TÉCNICOS SEGÚN HORARIO
    actualizar_estado_tecnicos_por_horario()
    
    # 2. Devolver los que quedaron con activo=1
    return cargar_tecnicos_activos()


def obtener_ultimo_tecnico_asignado():
    """
    Consulta la tabla configuracion_asignacion para ver quién fue el último.
    Retorna el ID del técnico (int) o None si no hay registros.
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor(pymysql.cursors.DictCursor)
        
        # Consultamos el registro con ID 1 que guarda la configuración
        cursor.execute("SELECT valor FROM configuracion_asignacion WHERE id = 1")
        fila = cursor.fetchone()
        
        conexion.close()
        
        if fila and fila['valor']:
            # El campo 'valor' guarda el ID del técnico (ej: "10")
            return int(fila['valor'])
        else:
            return None
            
    except Exception as e:
        print(f"⚠️ Error al leer configuracion_asignacion: {str(e)}")
        return None


def obtener_posicion_carrusel():
    """
    Obtiene la posición actual del carrusel.
    """
    return obtener_ultimo_tecnico_asignado()


def guardar_ultimo_tecnico_asignado(id_tecnico):
    """
    Actualiza la tabla configuracion_asignacion con el ID del técnico que acabamos de usar.
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor()
        
        # Usamos ON DUPLICATE KEY UPDATE para insertar si no existe, o actualizar si ya existe.
        # Guardamos el ID del técnico en la columna 'valor' y actualizamos la fecha.
        query = """
            INSERT INTO configuracion_asignacion (id, valor, fecha_actualizacion) 
            VALUES (1, %s, NOW()) 
            ON DUPLICATE KEY UPDATE 
                valor = %s, 
                fecha_actualizacion = NOW()
        """
        
        cursor.execute(query, (id_tecnico, id_tecnico))
        
        conexion.commit()
        conexion.close()
        # print(f"💾 Guardado en configuracion: Último técnico asignado = {id_tecnico}")
        
    except Exception as e:
        print(f"❌ Error al actualizar configuracion_asignacion: {str(e)}")


def obtener_tecnico_por_default(id_o_nombre_default):
    """
    Busca un técnico específico cuando la plantilla no usa carrusel.
    Busca por ID de sistema o por Nombre.
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor(pymysql.cursors.DictCursor)
        
        sql = "SELECT * FROM tecnicos WHERE id_sistema = %s OR nombre = %s OR id = %s LIMIT 1"
        cursor.execute(sql, (id_o_nombre_default, id_o_nombre_default, id_o_nombre_default))
        
        tecnico = cursor.fetchone()
        conexion.close()
        
        if tecnico:
            print(f"   🎯 Técnico Default encontrado: {tecnico['nombre']}")
            return tecnico
        else:
            print(f"   ⚠️ Técnico Default '{id_o_nombre_default}' no encontrado en BD local.")
            return None
            
    except Exception as e:
        print(f"Error al buscar técnico default: {str(e)}")
        return None


# ===================================================================
# ASIGNACIÓN ROTATIVA (CARRUSEL) - LÓGICA CORREGIDA
# ===================================================================
def seleccionar_siguiente_tecnico():
    """
    Selecciona el siguiente técnico disponible usando lógica de carrusel (Round Robin).
    Verifica en tiempo real las reglas de horario (Comida -20min, Salida, etc).
    """
    try:
        conexion = get_connection()
        cursor = conexion.cursor(pymysql.cursors.DictCursor)
        
        # 1. Obtener la hora y día actual
        hora_actual, dia_actual = obtener_info_tiempo_actual()
        print(f"\n🔄 INICIANDO CARRUSEL DE ASIGNACIÓN ({dia_actual} {hora_actual})")

        # 2. Obtener TODOS los técnicos ordenados (sin filtrar por activo aun)
        # Traemos también su horario para el día de HOY
        query = """
            SELECT t.id, t.nombre, t.correo, t.id_sistema,
                   h.hora_entrada, h.hora_salida, h.inicio_comida, h.fin_comida
            FROM tecnicos t
            LEFT JOIN horarios_tecnicos h ON t.id = h.id_tecnico AND h.dia_semana = %s
            WHERE t.modo_asignacion = 1  -- Solo consideramos técnicos en modo auto
            ORDER BY t.orden_asignacion ASC, t.id ASC
        """
        cursor.execute(query, (dia_actual,))
        tecnicos_candidatos = cursor.fetchall()

        if not tecnicos_candidatos:
            print("❌ No hay técnicos configurados para el día de hoy.")
            conexion.close()
            return None

        # 3. Obtener el ID del último técnico al que se le asignó
        ultimo_id = obtener_ultimo_tecnico_asignado() # Tu función existente
        
        # 4. Encontrar el índice de inicio
        indice_inicio = 0
        if ultimo_id:
            for i, tec in enumerate(tecnicos_candidatos):
                if tec['id'] == ultimo_id:
                    indice_inicio = i + 1 # Empezar por el siguiente
                    break
        
        # 5. Iterar cíclicamente buscando el primero que cumpla las reglas
        total_tecnicos = len(tecnicos_candidatos)
        tecnico_seleccionado = None
        razon_rechazo = ""

        print(f"   🔎 Buscando candidato entre {total_tecnicos} técnicos, iniciando después del ID {ultimo_id}...")

        for i in range(total_tecnicos):
            # Usamos módulo para dar la vuelta al array (carrusel infinito)
            indice_actual = (indice_inicio + i) % total_tecnicos
            candidato = tecnicos_candidatos[indice_actual]
            
            # Validar Horario estricto
            disponible, mensaje = es_tecnico_disponible_ahora(candidato)
            
            if disponible:
                tecnico_seleccionado = candidato
                print(f"   ✅ CANDIDATO VÁLIDO ENCONTRADO: {candidato['nombre']} (ID: {candidato['id']})")
                break
            else:
                # Solo logs para depuración
                # print(f"   ⏭️ Saltando a {candidato['nombre']}: {mensaje}")
                pass

        conexion.close()

        # 6. Guardar y retornar resultado
        if tecnico_seleccionado:
            guardar_ultimo_tecnico_asignado(tecnico_seleccionado['id'])
            
            # Registrar auditoría
            registrar_control_asignacion(
                id_tecnico=tecnico_seleccionado['id'],
                ultimo_id_asignado=ultimo_id,
                total_disponibles=total_tecnicos
            )
            return tecnico_seleccionado
        else:
            print("⚠️ ATENCIÓN: Se recorrió toda la lista y NINGÚN técnico cumple las reglas de horario ahora mismo.")
            return None

    except Exception as e:
        print(f"❌ Error crítico en carrusel: {str(e)}")
        traceback.print_exc()
        return None


def registrar_control_asignacion(id_tecnico, ultimo_id_asignado, total_disponibles):
    """Registra la asignación para auditoría"""
    try:
        inicializar_tablas_si_no_existen()
        
        conexion = get_connection()
        cursor = conexion.cursor()
        
        cursor.execute("""
            INSERT INTO control_asignacion 
            (id_tecnico, ultimo_id_asignado, total_disponibles, metodo) 
            VALUES (%s, %s, %s, 'carrusel')
        """, (id_tecnico, ultimo_id_asignado, total_disponibles))
        
        conexion.commit()
        conexion.close()
        # print(f"📝 Control de asignación registrado para técnico {id_tecnico}")
    except Exception as e:
        print(f"Error al registrar control de asignación: {str(e)}")


def actualizar_control_asignacion_con_ticket(id_ticket, id_tecnico):
    """Actualiza el control de asignación con el ID del ticket"""
    try:
        inicializar_tablas_si_no_existen()
        
        conexion = get_connection()
        cursor = conexion.cursor()
        
        cursor.execute("""
            UPDATE control_asignacion 
            SET id_ticket = %s 
            WHERE id_tecnico = %s 
            AND fecha_asignacion >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY fecha_asignacion DESC 
            LIMIT 1
        """, (id_ticket, id_tecnico))
        
        conexion.commit()
        conexion.close()
        # print(f"✅ Control_asignacion actualizado con ticket {id_ticket}")
    except Exception as e:
        print(f"⚠️ No se pudo actualizar control_asignacion: {str(e)}")


# ===================================================================
# GUARDAR ASIGNACIÓN
# ===================================================================
def guardar_asignacion(id_ticket, tecnico, grupo, plantilla, descripcion_original="", 
                        descripcion_limpia="", tiempo_procesamiento=0, 
                        palabras_clave="", confianza=0, respuesta_api=None,
                        analisis_gpt=None, asunto_ticket=""):
    """
    Guarda la asignación en la base de datos con análisis GPT.
    """
    print(f"\n💾 INTENTANDO GUARDAR ASIGNACIÓN EN BD:")
    print(f"   Ticket: {id_ticket}")
    print(f"   Técnico: {tecnico}")
    
    try:
        inicializar_tablas_si_no_existen()
        
        conexion = get_connection()
        cursor = conexion.cursor()
        
        respuesta_api_text = json.dumps(respuesta_api) if respuesta_api else None
        
        if isinstance(palabras_clave, list):
            palabras_clave_text = json.dumps(palabras_clave)
        else:
            palabras_clave_text = palabras_clave
        
        # Verificar si el ticket ya existe
        cursor.execute("SELECT id FROM tickets_asignados WHERE id_ticket = %s", (id_ticket,))
        existe = cursor.fetchone()
        
        valores = (
            id_ticket,
            tecnico,
            grupo[:255] if grupo else None,
            plantilla[:255] if plantilla else None,
            datetime.now(),
            respuesta_api_text[:65535] if respuesta_api_text else None,
            descripcion_original[:65535] if descripcion_original else None,
            descripcion_limpia[:65535] if descripcion_limpia else None,
            tiempo_procesamiento,
            palabras_clave_text[:65535] if palabras_clave_text else None,
            int(confianza)
        )
        
        if existe:
            print(f"⚠️ Ticket {id_ticket} ya existe en la BD, actualizando...")
            
            sql_update = """
                UPDATE tickets_asignados SET
                    usuario_tecnico = %s,
                    grupo = %s,
                    templete = %s,
                    fecha_asignacion = %s,
                    respuesta_api = %s,
                    descripcion_original = %s,
                    descripcion_limpia = %s,
                    tiempo_procesamiento = %s,
                    palabras_clave = %s,
                    confianza = %s
                WHERE id_ticket = %s
            """
            valores_update = valores[1:] + (valores[0],)
            cursor.execute(sql_update, valores_update)
            print(f"   ✅ Ticket actualizado en BD.")
            
        else:
            print(f"📝 Insertando nuevo ticket en BD...")
            sql_insert = """
                INSERT INTO tickets_asignados (
                    id_ticket, usuario_tecnico, grupo, templete, fecha_asignacion,
                    respuesta_api, descripcion_original, descripcion_limpia,
                    tiempo_procesamiento, palabras_clave, confianza
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql_insert, valores)
            print(f"   ✅ Guardado exitoso.")
        
        conexion.commit()
        
        if analisis_gpt:
            try:
                guardar_analisis_gpt(
                    ticket_id=id_ticket,
                    plantilla_id=None,
                    analisis_completo=analisis_gpt,
                    asunto_ticket=asunto_ticket
                )
            except Exception as e_log:
                print(f"   ⚠️ Error al guardar en analisis_gpt_log: {str(e_log)}")
        
        conexion.close()
        return True
        
    except Exception as e:
        print(f"❌ ERROR CRÍTICO al guardar asignación: {str(e)}")
        import traceback
        traceback.print_exc()
        return False


# ===================================================================
# ACTUALIZAR TICKET EN SERVICEDESK
# ===================================================================
def actualizar_ticket_con_tecnico(id_ticket, tecnico, plantilla=None):
    """
    Actualiza ticket asignándolo a un técnico específico con todos los campos de plantilla.
    Si tecnico es None, solo actualiza plantilla, grupo y categoria.
    """
    if not plantilla:
        payload = {}
    else:
        # Construcción base del payload
        payload = {
            "request": {
                "group": {
                    "id": str(plantilla.get("id_grupo")),
                    "name": plantilla.get("grupo", "Service Desk"),
                    "site": {
                        "id": "602"
                    }
                },
                "template": {
                    "id": "4",
                    "name": plantilla.get("plantilla_incidente", "Default Request")
                },
                "request_type": {
                    "name": "Requerimiento",
                    "id": "3"
                }
            }
        }

        # AGREGAR TÉCNICO SOLO SI EXISTE
        if tecnico:
            payload["request"]["technician"] = {
                "email_id": tecnico.get("correo"),
                "id": str(tecnico.get("id_sistema"))
            }
            # Cambiar estado a Asignado solo si hay técnico
            payload["request"]["status"] = {
                "color": "#f88888",
                "name": "Asignado",
                "id": "2"
            }
            # Mode solo si hay asignación activa (opcional)
            payload["request"]["mode"] = {
                "name": "Correo Electrónico",
                "id": "1"
            }

        # Agregar campos opcionales de la plantilla
        if plantilla.get("categoria"):
            payload["request"]["category"] = {
                "name": plantilla.get("categoria")
            }
        
        if plantilla.get("subcategoria"):
            payload["request"]["subcategory"] = {
                "name": plantilla.get("subcategoria")
            }
        
        if plantilla.get("articulo"):
            payload["request"]["item"] = {
                "name": plantilla.get("articulo")
            }

    print("\n====== PAYLOAD COMPLETO ACTUALIZACIÓN ======")
    print(json.dumps(payload, indent=4))
    print("============================================\n")

    url = f"{BASE_URL}requests/{id_ticket}"
    headers = {"authtoken": API_KEY}
    data = {"input_data": json.dumps(payload)}

    respuesta = requests.put(url, headers=headers, data=data, verify=False, timeout=30)

    print("\nRespuesta del servidor externo:")
    print(f"Código de estado: {respuesta.status_code}")
    print("=========================================\n")

    return respuesta


# ===================================================================
# PROCESAR TICKET INDIVIDUAL
# ===================================================================
# ===================================================================
# PROCESAR TICKET INDIVIDUAL (LÓGICA CORREGIDA)
# ===================================================================
def procesar_ticket_con_tecnico_gpt(id_ticket):
    """
    Procesa ticket y asigna técnico.
    REGLA DE ORO: Si no hay técnico default, usa carrusel de activos.
    Si no se requiere técnico, actualiza solo la plantilla y guarda en BD.
    """
    print(f"\n{'='*100}")
    print(f"🚀 PROCESANDO TICKET #{id_ticket}")
    
    tiempo_inicio = datetime.now()
    
    try:
        datos = servicedesk_get(f"requests/{id_ticket}")
    except Exception as e:
        print(f"❌ ERROR API: {str(e)}")
        return {"ticket": id_ticket, "error": str(e), "procesado": False}

    if "request" not in datos:
        return {"ticket": id_ticket, "error": "Ticket no encontrado", "procesado": False}

    solicitud = datos["request"]
    estado = solicitud["status"]["name"]
    origen = solicitud["requester"]["name"]
    articulo = solicitud["subject"]
    
    # Limpieza de datos
    articulo_limpio = articulo.strip()
    descripcion_html = solicitud.get("description", "")
    descripcion_limpia = extraer_texto_de_html(descripcion_html)
    
    if estado != "Abierta":
        print(f"⏸️ Ticket {id_ticket} omitido (Estado actual: '{estado}')")
        return {"ticket": id_ticket, "mensaje": "No está abierto", "procesado": False}

    # PASO 1: Buscar plantilla
    print(f"\n🔎 BUSCANDO PLANTILLA CON GPT:")
    plantilla = coincidir_plantilla_con_gpt(origen, articulo_limpio, descripcion_limpia)
    
    if not plantilla:
        return {"ticket": id_ticket, "mensaje": "No se encontró plantilla", "procesado": False}

    # ==============================================================================
    # PASO 2: LOGICA DE ASIGNACIÓN
    # ==============================================================================
    
    requiere_asignacion = plantilla.get("tencifo_default") 
    
    tecnico = None
    metodo_asignacion = ""
    nombre_tecnico_para_bd = "Sin Asignación" # Valor por defecto para BD

    # REGLA PRINCIPAL: Solo asignar si la plantilla tiene activado el flag
    if requiere_asignacion is None or requiere_asignacion == '' or requiere_asignacion == 1:
        print(f"\n⚙️ La plantilla '{plantilla.get('plantilla_incidente')}' requiere asignación automática.")
        
        # Ejecutamos el carrusel inteligente
        tecnico = seleccionar_siguiente_tecnico()
        
        if tecnico:
            metodo_asignacion = "Automático - Carrusel"
            nombre_tecnico_para_bd = tecnico["nombre"]
        else:
            print("⚠️ No se pudo asignar técnico (Todos ocupados/Fuera de horario).")
            metodo_asignacion = "Pendiente (Sin Disponibilidad)"
            
    else:
        # Si asigna_tecnico es 0
        print(f"\nℹ️ La plantilla no requiere asignación automática (asigna_tecnico != 1).")
        metodo_asignacion = "Solo Clasificación"
        nombre_tecnico_para_bd = "No Requerido"

        if plantilla.get("tecnico_default"):
             print(f"   Usando técnico default fijo: {plantilla.get('tecnico_default')}")
             # Aquí iría lógica extra si quisieras buscar el ID del técnico default
             pass

    # Validación: Si se requería técnico y falló el carrusel, abortamos (Opcional, según tu regla de negocio)
    # Si quieres que se guarde como "Sin Asignación" aunque falle el carrusel, comenta este bloque IF
    if not tecnico and (requiere_asignacion == 1):
        return {
            "ticket": id_ticket, 
            "error": "Carrusel no encontró técnicos disponibles", 
            "plantilla": plantilla.get("plantilla_incidente"),
            "procesado": False
        }
    
    # PASO 3: Actualizar en ServiceDesk (Con o Sin Técnico)
    print(f"\n🔄 ACTUALIZANDO EN SERVICEDESK ({metodo_asignacion})...")
    # Nota: Esta función ya fue modificada para aceptar tecnico=None
    respuesta = actualizar_ticket_con_tecnico(id_ticket, tecnico, plantilla)
    
    tiempo_fin = datetime.now()
    tiempo_procesamiento = (tiempo_fin - tiempo_inicio).total_seconds()
    
    if respuesta.status_code != 200:
        return {"ticket": id_ticket, "error": f"API Error: {respuesta.status_code}", "procesado": False}
    
    # PASO 4: Guardar Historial y Logs en BD (AHORA SIEMPRE SE EJECUTA)
    try:
        # Recuperamos análisis previo si existe en memoria o re-generamos estructura
        resultado_gpt = analizar_con_gpt(articulo_limpio, descripcion_limpia, [plantilla])
        confianza = resultado_gpt.get("analisis_gpt", {}).get("confianza", 0)
        
        guardar_asignacion(
            id_ticket=id_ticket,
            tecnico=nombre_tecnico_para_bd, # Usamos el nombre calculado arriba
            grupo=plantilla.get("grupo"),
            plantilla=plantilla.get("plantilla_incidente"),
            descripcion_original=descripcion_html,
            descripcion_limpia=descripcion_limpia,
            tiempo_procesamiento=tiempo_procesamiento,
            palabras_clave=str(resultado_gpt.get("analisis_gpt", {}).get("coincidencias_asunto", [])),
            confianza=confianza,
            respuesta_api={"status": "success", "code": respuesta.status_code},
            analisis_gpt=resultado_gpt,
            asunto_ticket=articulo_limpio
        )
        
        # Solo actualizamos control de carrusel si hubo un técnico real
        if tecnico:
            actualizar_control_asignacion_con_ticket(id_ticket, tecnico['id'])
        
    except Exception as e:
        print(f"⚠️ Error no crítico guardando logs: {str(e)}")
        import traceback
        traceback.print_exc()

    return {
        "ticket": id_ticket,
        "tecnico": nombre_tecnico_para_bd,
        "metodo": metodo_asignacion,
        "plantilla": plantilla.get("plantilla_incidente"),
        "status": "Asignado/Actualizado",
        "procesado": True
    }
def es_tecnico_disponible_ahora(horario_tecnico):
    """
    Verifica si un técnico puede recibir tickets en este preciso instante
    basándose en las reglas de 20 minutos antes de comida/salida.
    """
    if not horario_tecnico:
        return False, "Sin horario configurado"

    ahora = datetime.now()
    hora_actual = ahora.time()

    # Convertir strings o timedeltas a objetos time
    try:
        entrada = convertir_a_tiempo(horario_tecnico['hora_entrada'])
        salida = convertir_a_tiempo(horario_tecnico['hora_salida'])
        inicio_comida = convertir_a_tiempo(horario_tecnico['inicio_comida'])
        fin_comida = convertir_a_tiempo(horario_tecnico['fin_comida'])
    except Exception as e:
        return False, f"Error formato hora: {e}"

    # 1. Verificar si está dentro del turno general
    if not esta_dentro_rango_horario(hora_actual, entrada, salida):
        return False, "Fuera de horario laboral"

    # 2. Regla: 20 minutos antes de la SALIDA
    # Calculamos la hora límite de salida (Salida - 20 min)
    fecha_base = datetime.today()
    dt_salida = datetime.combine(fecha_base, salida)
    limite_salida = (dt_salida - timedelta(minutes=20)).time()
    
    if esta_dentro_rango_horario(hora_actual, limite_salida, salida):
        return False, "20 min antes de salida"

    # 3. Regla: Comida (Incluye 20 min antes + Tiempo de comida)
    if inicio_comida and fin_comida:
        # Calcular inicio de bloqueo (Inicio comida - 20 min)
        dt_inicio_comida = datetime.combine(fecha_base, inicio_comida)
        inicio_bloqueo_comida = (dt_inicio_comida - timedelta(minutes=20)).time()

        # Si la hora actual es >= (comida - 20min) Y hora actual <= fin comida
        # Nota: Usamos lógica de rangos para abarcar todo el bloque
        if esta_dentro_rango_horario(hora_actual, inicio_bloqueo_comida, fin_comida):
             # Desglosamos la razón para logs
            if esta_dentro_rango_horario(hora_actual, inicio_bloqueo_comida, inicio_comida):
                return False, "20 min antes de comida"
            else:
                return False, "En horario de comida"

    return True, "Disponible"
# ===================================================================
# ENDPOINTS PRINCIPALES
# ===================================================================
@app.route("/procesar_ticket_gpt/<id_ticket>", methods=["GET"])
def endpoint_procesar_ticket_gpt(id_ticket):
    """Procesa ticket individual usando GPT-4o"""
    resultado = procesar_ticket_con_tecnico_gpt(id_ticket)
    return jsonify(resultado)


@app.route("/procesar_todos_gpt", methods=["GET"])
def endpoint_procesar_todos_gpt():
    """Procesa todos los tickets usando GPT-4o"""
    print(f"\n{'='*100}")
    print(f"🚀 INICIANDO PROCESAMIENTO MASIVO DE TICKETS CON GPT-4o Prueba general")
    print(f"   ⏰ Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*100}")
    
    # 1. Actualizar estado de técnicos antes de empezar
    actualizar_estado_tecnicos_por_horario()
    
    datos = servicedesk_get("requests")

    if "requests" not in datos:
        mensaje_error = "No se pudieron obtener los tickets desde ServiceDesk"
        print(f"❌ {mensaje_error}")
        return jsonify({"error": mensaje_error})

    total_tickets = len(datos["requests"])
    print(f"📊 Total de tickets abiertos encontrados: {total_tickets}")
    
    resultados = []
    procesados = 0
    no_procesados = 0
    tickets_asignados = []
    
    for indice, solicitud in enumerate(datos["requests"], 1):
        id_ticket = solicitud["id"]
        print(f"\n[{indice}/{total_tickets}] 🎫 Procesando ticket #{id_ticket}")
        
        resultado = procesar_ticket_con_tecnico_gpt(id_ticket)
        resultados.append(resultado)
        
        if resultado.get("procesado"):
            procesados += 1
            tickets_asignados.append(id_ticket)
        else:
            no_procesados += 1
    
    print(f"\n{'='*100}")
    print(f"📊 RESUMEN FINAL:")
    print(f"   📈 Total analizados: {total_tickets}")
    print(f"   ✅ Procesados: {procesados}")
    print(f"   ❌ No procesados: {no_procesados}")
    print(f"{'='*100}")
    
    return jsonify({
        "resumen 1": {
            "total_tickets": total_tickets,
            "procesados": procesados,
            "no_procesados": no_procesados,
            "tickets_asignados_ids": tickets_asignados,
            "Estado" : "Activo"
        },
        "detalles": resultados
    })


@app.route("/ver_tecnicos_disponibles", methods=["GET"])
def endpoint_ver_tecnicos_disponibles():
    """Muestra los técnicos disponibles actualmente"""
    tecnicos = obtener_tecnicos_disponibles()
    return jsonify(tecnicos)


@app.route("/actualizar_estado_tecnicos", methods=["GET"])
def endpoint_actualizar_estado_tecnicos():
    """Endpoint para actualizar manualmente el estado de los técnicos"""
    resultado = actualizar_estado_tecnicos_por_horario()
    return jsonify({"success": resultado})


if __name__ == "__main__":
    print(f"\n{'*'*100}")
    print(f"🚀 API DE ASIGNACIÓN AUTOMÁTICA INICIADA")
    print(f"   ⏰ Hora de inicio: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"   ⚙️  Modo Carrusel: Basado en ACTIVO=1")
    print(f"{'*'*100}\n")
    
    inicializar_tablas_si_no_existen()
    app.run(port=5000, debug=True, host='0.0.0.0')