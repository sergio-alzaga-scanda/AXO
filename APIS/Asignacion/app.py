from flask import Flask, jsonify
import requests
import pymysql
import json
from db import get_connection
from datetime import datetime, time, timedelta
import warnings
import re

app = Flask(__name__)

BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/"
API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401"


# ===================================================================
# FUNCIONES AUXILIARES
# ===================================================================
def get_current_time_info():
    """Obtiene la hora actual y el día de la semana actual en inglés corto"""
    now = datetime.now()
    current_time = now.time()
    
    # Días en inglés corto (como están en la BD: Mon, Tue, Wed, Thu, Fri, Sat, Sun)
    days_mapping = {
        0: "Mon",  # Lunes
        1: "Tue",  # Martes
        2: "Wed",  # Miércoles
        3: "Thu",  # Jueves
        4: "Fri",  # Viernes
        5: "Sat",  # Sábado
        6: "Sun"   # Domingo
    }
    
    current_day = days_mapping[now.weekday()]
    
    return current_time, current_day


def is_within_time_range(check_time, start_time, end_time):
    """Verifica si una hora está dentro de un rango"""
    if start_time <= end_time:
        # Rango normal (ej: 09:00-18:00)
        return start_time <= check_time <= end_time
    else:
        # Rango que cruza medianoche (ej: 22:00-06:00)
        return check_time >= start_time or check_time <= end_time


def subtract_minutes(time_obj, minutes):
    """Resta minutos a un objeto time"""
    dummy_datetime = datetime.combine(datetime.today(), time_obj)
    new_datetime = dummy_datetime - timedelta(minutes=minutes)
    return new_datetime.time()


def extract_text_from_html(html_content):
    """Extrae texto limpio de contenido HTML"""
    if not html_content:
        return ""
    
    # Eliminar etiquetas HTML
    text = re.sub(r'<[^>]+>', ' ', html_content)
    # Eliminar múltiples espacios y saltos de línea
    text = re.sub(r'\s+', ' ', text)
    # Eliminar el aviso de confidencialidad (si existe)
    text = re.sub(r'Aviso de Confidencialidad.*?Confidentiality Notice.*', '', text, flags=re.DOTALL)
    text = re.sub(r'Confidentiality Notice.*', '', text, flags=re.DOTALL)
    
    return text.strip()


# ===================================================================
# REQUEST GENÉRICO (GET y PUT) CON LOG
# ===================================================================
def servicedesk_get(endpoint):
    url = BASE_URL + endpoint
    print("\n====== GET ======")
    print("URL:", url)

    warnings.filterwarnings("ignore", message="Unverified HTTPS request")
    response = requests.get(
        url,
        headers={
            "TECHNICIAN_KEY": API_KEY,
            "Content-Type": "application/json"
        },
        verify=False
    )

    print("Respuesta:", response.text[:500] if response.text else "Vacía")
    print("=================\n")

    return response.json()


def servicedesk_put(endpoint, payload_json):
    url = BASE_URL + endpoint

    print("\n====== PUT ======")
    print("URL:", url)
    print("PAYLOAD ENVIADO:")
    print(payload_json)
    print("=================")

    warnings.filterwarnings("ignore", message="Unverified HTTPS request")
    response = requests.put(
        url,
        headers={
            "TECHNICIAN_KEY": API_KEY,
            "Content-Type": "application/json"
        },
        data=payload_json,
        verify=False
    )

    print("\nRespuesta del servidor externo:")
    print(response.text[:500] if response.text else "Vacía")
    print("=====================\n")

    return response.json()


# ===================================================================
# LECTURA DE TABLAS
# ===================================================================
def load_templates():
    """Carga solo plantillas con status=1"""
    conn = get_connection()
    cur = conn.cursor(pymysql.cursors.DictCursor)
    cur.execute("SELECT * FROM plantillas_incidentes WHERE status = 1")
    rows = cur.fetchall()
    conn.close()
    return rows


def load_technicians():
    """
    Trae técnicos activos con su id_sistema para ServiceDesk.
    """
    conn = get_connection()
    cur = conn.cursor(pymysql.cursors.DictCursor)
    cur.execute("SELECT * FROM tecnicos WHERE activo = 1")
    rows = cur.fetchall()
    conn.close()
    return rows


def get_technician_schedule(id_tecnico):
    """Obtiene el horario del técnico para el día actual"""
    conn = get_connection()
    cur = conn.cursor(pymysql.cursors.DictCursor)
    
    current_time, current_day = get_current_time_info()
    
    cur.execute("""
        SELECT * FROM horarios_tecnicos 
        WHERE id_tecnico = %s AND dia_semana = %s
    """, (id_tecnico, current_day))
    
    schedule = cur.fetchone()
    conn.close()
    
    return schedule


def is_technician_available(technician):
    """
    Verifica si un técnico está disponible según su horario.
    - No disponible si está fuera de su horario
    - No disponible 20 min antes de salida
    - No disponible 20 min antes de comida
    - No disponible durante comida
    """
    schedule = get_technician_schedule(technician['id'])
    
    if not schedule:
        print(f"Técnico {technician['nombre']} no tiene horario para hoy")
        return False
    
    current_time, current_day = get_current_time_info()
    
    # Convertir tiempos de la BD a objetos time
    hora_entrada = schedule['hora_entrada']
    hora_salida = schedule['hora_salida']
    inicio_comida = schedule['inicio_comida']
    fin_comida = schedule['fin_comida']
    
    # Verificar si está en su horario laboral
    if not is_within_time_range(current_time, hora_entrada, hora_salida):
        return False
    
    # Verificar si está en horario de comida
    if inicio_comida and fin_comida:
        if is_within_time_range(current_time, inicio_comida, fin_comida):
            return False
        
        # Verificar 20 minutos antes de la comida
        if inicio_comida != hora_entrada:  # Solo si hay tiempo antes de la comida
            comida_buffer = subtract_minutes(inicio_comida, 20)
            
            # Verificar si estamos en el buffer de 20 min antes de la comida
            if inicio_comida > hora_entrada:  # Comida después de entrada
                if current_time >= comida_buffer and current_time < inicio_comida:
                    return False
            else:  # Comida cruza medianoche
                if current_time >= comida_buffer or current_time < inicio_comida:
                    return False
    
    # Verificar 20 minutos antes de la salida
    if hora_salida != hora_entrada:  # Evitar división por cero
        salida_buffer = subtract_minutes(hora_salida, 20)
        
        # Verificar si estamos en el buffer de 20 min antes de salida
        if hora_salida > hora_entrada:  # Salida después de entrada
            if current_time >= salida_buffer and current_time < hora_salida:
                return False
        else:  # Salida cruza medianoche
            if current_time >= salida_buffer or current_time < hora_salida:
                return False
    
    return True


def get_available_technicians():
    """
    Obtiene la lista de técnicos disponibles (activos y dentro de su horario)
    """
    all_techs = load_technicians()
    available_techs = []
    
    for tech in all_techs:
        if is_technician_available(tech):
            available_techs.append(tech)
    
    return available_techs


def get_assignment_position():
    conn = get_connection()
    cur = conn.cursor(pymysql.cursors.DictCursor)
    cur.execute("SELECT valor FROM configuracion_asignacion WHERE id = 1")
    row = cur.fetchone()

    if row:
        pos = int(row["valor"])
    else:
        pos = 0
        cur2 = conn.cursor()
        cur2.execute("INSERT INTO configuracion_asignacion (id, valor) VALUES (1, 0)")
        conn.commit()

    conn.close()
    return pos


def set_assignment_position(pos):
    conn = get_connection()
    cur = conn.cursor()
    cur.execute("UPDATE configuracion_asignacion SET valor = %s WHERE id = 1", (pos,))
    conn.commit()
    conn.close()


# ===================================================================
# GUARDAR ASIGNACIÓN
# ===================================================================
def save_assignment(id_ticket, tecnico, grupo, template):
    conn = get_connection()
    cur = conn.cursor()
    cur.execute("""
        INSERT INTO tickets_asignados (id_ticket, usuario_tecnico, grupo, templete, fecha_asignacion)
        VALUES (%s, %s, %s, %s, %s)
    """, (id_ticket, tecnico, grupo, template, datetime.now()))
    conn.commit()
    conn.close()


# ===================================================================
# MATCH DE PLANTILLAS MEJORADO - EVALUA ARTÍCULO Y DESCRIPCIÓN
# ===================================================================
def match_template(origen, articulo, descripcion_ticket=""):
    """
    Busca plantilla que coincida, evaluando:
    1. Campo 'articulo' de la plantilla
    2. Campo 'descripcion' de la plantilla
    Ambos campos se comparan contra el 'articulo' y 'descripcion' del ticket
    """
    templates = load_templates()
    
    origen_norm = origen.lower().strip() if origen else ""
    articulo_norm = articulo.lower().strip() if articulo else ""
    descripcion_norm = descripcion_ticket.lower().strip() if descripcion_ticket else ""
    
    texto_completo_ticket = f"{articulo_norm} {descripcion_norm}".strip()
    
    print(f"\n{'='*80}")
    print("🔍 BUSCANDO PLANTILLA PARA:")
    print(f"   Origen: '{origen}' (normalizado: '{origen_norm}')")
    print(f"   Artículo: '{articulo}'")
    print(f"   Descripción del ticket (limpia): '{descripcion_ticket[:100]}...'" if descripcion_ticket else "   Descripción del ticket: No disponible")
    print(f"   Texto completo para búsqueda: '{texto_completo_ticket[:150]}...'")
    print(f"   Total plantillas activas (status=1): {len(templates)}")
    print(f"{'='*80}")
    
    if not templates:
        print("⚠️ No hay plantillas activas (status=1) en la base de datos")
        return None
    
    mejor_coincidencia = None
    mejor_puntaje = 0
    coincidencias_detalladas = []
    
    for idx, row in enumerate(templates, 1):
        # IMPORTANTE: En la tabla real, 'origen' puede ser NULL
        row_origen = (row["origen"] or "").lower().strip() if row["origen"] else ""
        row_articulo = (row["articulo"] or "").lower().strip() if row["articulo"] else ""
        row_descripcion = (row["descripcion"] or "").lower().strip() if row["descripcion"] else ""
        row_categoria = row.get("categoria", "")
        row_subcategoria = row.get("subcategoria", "")
        
        print(f"\n📋 Evaluando Plantilla #{idx}:")
        print(f"   ID: {row['id']}")
        print(f"   Nombre: {row['plantilla_incidente']}")
        print(f"   Origen en plantilla: '{row['origen'] or 'NULL'}' (normalizado: '{row_origen}')")
        print(f"   Artículo en plantilla: '{row['articulo'] or 'Vacío'}'")
        print(f"   Descripción en plantilla: '{row['descripcion'][:50] or 'Sin descripción'}...'")
        print(f"   Categoría: {row_categoria}")
        print(f"   Subcategoría: {row_subcategoria}")
        print(f"   Grupo: {row['grupo']}")
        
        # Verificar origen primero (si la plantilla tiene origen definido)
        if row_origen:  # Solo si la plantilla tiene origen configurado
            if origen_norm != row_origen:
                print(f"   ❌ Origen no coincide: '{origen_norm}' != '{row_origen}'")
                continue
            print(f"   ✅ Origen coincide!")
        else:
            print(f"   ℹ️  Plantilla sin origen definido - evaluando solo por contenido")
        
        puntaje_total = 0
        palabras_coincidentes_articulo = []
        palabras_coincidentes_descripcion = []
        
        # 1. EVALUAR COINCIDENCIAS EN CAMPO 'ARTÍCULO' DE LA PLANTILLA
        if row_articulo:
            palabras_articulo = [p for p in row_articulo.split() if len(p) > 2]
            if palabras_articulo:
                print(f"   Palabras clave en 'artículo' de plantilla: {palabras_articulo}")
                
                for palabra in palabras_articulo:
                    if palabra in texto_completo_ticket:
                        palabras_coincidentes_articulo.append(palabra)
                        puntaje_total += 3  # Peso mayor para coincidencias en artículo de plantilla
                
                if palabras_coincidentes_articulo:
                    print(f"   ✅ Coincidencias en 'artículo': {palabras_coincidentes_articulo}")
                    print(f"   Puntos por artículo: {len(palabras_coincidentes_articulo) * 3}")
            else:
                print(f"   ℹ️  Artículo en plantilla no tiene palabras clave significativas")
        
        # 2. EVALUAR COINCIDENCIAS EN CAMPO 'DESCRIPCIÓN' DE LA PLANTILLA
        if row_descripcion:
            # Extraer palabras clave de la descripción (eliminar palabras comunes)
            palabras_descripcion = [p for p in row_descripcion.split() 
                                  if len(p) > 3 and p not in ['para', 'con', 'los', 'las', 'del', 'de', 'la', 'el', 'en', 'y', 'o', 'a', 'se', 'que']]
            
            if palabras_descripcion:
                print(f"   Palabras clave en 'descripción' de plantilla: {palabras_descripcion[:10]}...")
                
                for palabra in palabras_descripcion:
                    if palabra in texto_completo_ticket:
                        palabras_coincidentes_descripcion.append(palabra)
                        puntaje_total += 2  # Peso medio para coincidencias en descripción
                
                if palabras_coincidentes_descripcion:
                    print(f"   ✅ Coincidencias en 'descripción': {palabras_coincidentes_descripcion[:5]}")
                    print(f"   Puntos por descripción: {len(palabras_coincidentes_descripcion) * 2}")
            else:
                print(f"   ℹ️  Descripción en plantilla no tiene palabras clave significativas")
        
        # Calcular puntaje total
        total_coincidencias = len(palabras_coincidentes_articulo) + len(palabras_coincidentes_descripcion)
        
        if total_coincidencias > 0:
            print(f"   📊 PUNTAJE TOTAL: {puntaje_total}")
            print(f"   Coincidencias totales: {total_coincidencias}")
            
            # Guardar detalles de la coincidencia
            coincidencia_info = {
                "plantilla": row,
                "puntaje": puntaje_total,
                "coincidencias_articulo": palabras_coincidentes_articulo,
                "coincidencias_descripcion": palabras_coincidentes_descripcion,
                "total_coincidencias": total_coincidencias
            }
            
            coincidencias_detalladas.append(coincidencia_info)
            
            # Actualizar mejor coincidencia
            if puntaje_total > mejor_puntaje:
                mejor_puntaje = puntaje_total
                mejor_coincidencia = row
                print(f"   🏆 NUEVA MEJOR COINCIDENCIA (puntaje: {puntaje_total})")
        else:
            print(f"   ❌ No hay coincidencias en artículo ni descripción")
    
    # Mostrar resumen de todas las coincidencias
    if coincidencias_detalladas:
        print(f"\n{'='*80}")
        print("📊 RESUMEN DE COINCIDENCIAS ENCONTRADAS:")
        for idx, coincidencia in enumerate(sorted(coincidencias_detalladas, key=lambda x: x['puntaje'], reverse=True), 1):
            plantilla = coincidencia['plantilla']
            print(f"\n   #{idx} - Plantilla ID: {plantilla['id']}")
            print(f"      Nombre: {plantilla['plantilla_incidente']}")
            print(f"      Puntaje: {coincidencia['puntaje']}")
            print(f"      Coincidencias en artículo: {coincidencia['coincidencias_articulo']}")
            print(f"      Coincidencias en descripción: {coincidencia['coincidencias_descripcion'][:5]}")
            print(f"      Total coincidencias: {coincidencia['total_coincidencias']}")
        
        print(f"\n🏆 MEJOR COINCIDENCIA SELECCIONADA:")
        print(f"   Plantilla: {mejor_coincidencia['plantilla_incidente']}")
        print(f"   Puntaje: {mejor_puntaje}")
        print(f"   Grupo: {mejor_coincidencia['grupo']}")
        print(f"{'='*80}")
        
        return mejor_coincidencia
    else:
        print(f"\n{'='*80}")
        print("🔍 RESUMEN DE BÚSQUEDA:")
        print(f"   Se evaluaron {len(templates)} plantillas activas")
        print(f"   Origen buscado: '{origen}'")
        print(f"   Artículo buscado: '{articulo}'")
        print(f"   No se encontraron coincidencias en campos 'artículo' o 'descripción'")
        print(f"{'='*80}")
        
        return None


# ===================================================================
# ACTUALIZAR TICKET EN SERVICEDESK
# ===================================================================
def update_ticket(id_ticket, group_id, group_name, site_id, template_id, template_name, technician=None):
    payload = {
        "request": {
            "group": {
                "id": str(group_id),
                "name": group_name,
                "site": {
                    "id": "602"
                }
            },
            "template": {
                "id": str(template_id),
                "name": template_name
            }
        }
    }

    if technician:
        payload["request"]["technician"] = {
            "email_id": technician.get("correo"),
            "id": str(technician.get("id_sistema"))
        }

    print("\n====== PAYLOAD FINAL ======")
    print(json.dumps(payload, indent=4))
    print("===========================\n")

    url = f"{BASE_URL}requests/{id_ticket}"
    headers = {"authtoken": API_KEY}
    data = {"input_data": json.dumps(payload)}

    response = requests.put(url, headers=headers, data=data, verify=False)

    print("\nRespuesta del servidor externo:")
    print(response.text[:500] if response.text else "Vacía")
    print("=====================\n")

    return response.json()


# ===================================================================
# PROCESAR TICKET INDIVIDUAL CON LOGS MEJORADOS
# ===================================================================
def procesar_ticket_grupo(id_ticket):
    """
    Versión que solo asigna a grupo si hay plantilla, 
    NO asigna a técnicos individuales
    """
    print(f"\n{'='*100}")
    print(f"🚀 INICIANDO PROCESAMIENTO DEL TICKET #{id_ticket}")
    print(f"   Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*100}")
    
    data = servicedesk_get(f"requests/{id_ticket}")

    if "request" not in data:
        error_msg = f"❌ ERROR: Ticket {id_ticket} no encontrado en ServiceDesk"
        print(error_msg)
        return {"error": "Ticket no encontrado", "detalles": error_msg}

    req = data["request"]

    status = req["status"]["name"]
    origen = req["requester"]["name"]
    articulo = req["subject"]
    
    # Obtener categoría y subcategoría de forma segura
    categoria = "No especificada"
    subcategoria = "No especificada"
    
    if req.get("category") and isinstance(req["category"], dict):
        categoria = req["category"].get("name", "No especificada")
    
    if req.get("subcategory") and isinstance(req["subcategory"], dict):
        subcategoria = req["subcategory"].get("name", "No especificada")
    
    # Obtener y limpiar la descripción del ticket
    descripcion_html = req.get("description", "")
    descripcion_limpia = extract_text_from_html(descripcion_html)
    
    # Limitar longitud para logs
    descripcion_mostrar = descripcion_limpia[:200] + "..." if len(descripcion_limpia) > 200 else descripcion_limpia

    print(f"\n📋 DATOS DEL TICKET #{id_ticket}:")
    print(f"   Status: {status}")
    print(f"   Origen: {origen}")
    print(f"   Artículo/Asunto: {articulo}")
    print(f"   Descripción (limpia): {descripcion_mostrar}")
    print(f"   Categoría: {categoria}")
    print(f"   Subcategoría: {subcategoria}")
    print(f"{'-'*60}")

    # Solo procesar tickets Abiertos
    if status != "Abierta":
        mensaje = f"Ticket {id_ticket} no procesado porque no está en estado 'Abierta' (estado actual: '{status}')"
        print(f"⏸️  {mensaje}")
        return {
            "ticket": id_ticket,
            "mensaje": mensaje,
            "estado_actual": status,
            "procesado": False
        }

    # Buscar plantilla que coincida (evalúa artículo y descripción de plantilla)
    match = match_template(origen, articulo, descripcion_limpia)

    # -------------------------------------------------------------
    # CASO 1: Si hay plantilla → se asigna al grupo
    # -------------------------------------------------------------
    if match:
        group_id = match["id_grupo"]
        group_name = match["grupo"]
        template_name = match["plantilla_incidente"]
        template_id = 4  # Valor por defecto
        site_id = 602    # Valor por defecto
        descripcion_plantilla = match.get("descripcion", "Sin descripción disponible")
        
        # Construir mensaje detallado de la razón
        razon_detallada = f"Coincidencia encontrada"
        
        if match.get("origen"):
            razon_detallada += f" por origen '{match['origen']}'"
        
        if match.get("articulo"):
            razon_detallada += f", palabras clave en artículo: '{match['articulo']}'"
        
        if match.get("descripcion"):
            razon_detallada += f", descripción: '{descripcion_plantilla[:80]}...'"

        print(f"\n✅ PLANTILLA ENCONTRADA Y SELECCIONADA:")
        print(f"   ID Plantilla: {match['id']}")
        print(f"   Nombre Plantilla: {template_name}")
        print(f"   Grupo Destino: {group_name} (ID: {group_id})")
        print(f"   Descripción de uso: {descripcion_plantilla[:100]}...")
        print(f"   Razón de Coincidencia: {razon_detallada}")
        print(f"   Texto Evaluado del Ticket:")
        print(f"     - Origen: '{origen}'")
        print(f"     - Artículo: '{articulo}'")
        print(f"     - Descripción: '{descripcion_mostrar}'")
        
        # Actualizar ticket con grupo y plantilla
        resultado_update = update_ticket(id_ticket, group_id, group_name, site_id, template_id, template_name)
        
        # Guardar en BD
        save_assignment(id_ticket, "N/A", group_name, template_name)
        
        print(f"\n✅ ASIGNACIÓN COMPLETADA:")
        print(f"   Ticket #{id_ticket} asignado al grupo '{group_name}'")
        print(f"   Plantilla aplicada: '{template_name}'")
        print(f"   Guardado en historial de asignaciones")

        return {
            "ticket": id_ticket,
            "grupo": group_name,
            "template": template_name,
            "descripcion_plantilla": descripcion_plantilla,
            "tecnico": "N/A",
            "tipo": "Asignado por plantilla a grupo",
            "texto_evaluado": {
                "origen": origen,
                "articulo": articulo,
                "descripcion_ticket": descripcion_mostrar,
                "categoria": categoria,
                "subcategoria": subcategoria
            },
            "razon_coincidencia": razon_detallada,
            "detalles_plantilla": {
                "id": match['id'],
                "nombre": match['plantilla_incidente'],
                "origen_plantilla": match.get('origen', 'No definido'),
                "articulo_plantilla": match.get('articulo', ''),
                "descripcion_plantilla": descripcion_plantilla,
                "categoria_plantilla": match.get('categoria'),
                "subcategoria_plantilla": match.get('subcategoria')
            },
            "procesado": True,
            "fecha_procesamiento": datetime.now().isoformat()
        }

    # -------------------------------------------------------------
    # CASO 2: NO hay plantilla → NO se asigna
    # -------------------------------------------------------------
    print(f"\n⚠️ NO SE ENCONTRÓ PLANTILLA ADECUADA")
    print(f"   Ticket #{id_ticket} NO será asignado")
    print(f"   Razón: No hay coincidencia entre:")
    print(f"     - Origen del ticket: '{origen}'")
    print(f"     - Artículo/Asunto: '{articulo}'")
    print(f"     - Descripción: '{descripcion_mostrar}'")
    print(f"     - Y los campos 'artículo' o 'descripción' de las plantillas activas")
    print(f"   Acción requerida: Revisión manual del ticket")
    
    print(f"\n📝 RESUMEN PARA REVISIÓN MANUAL:")
    print(f"   • Ticket ID: {id_ticket}")
    print(f"   • Origen: {origen}")
    print(f"   • Asunto: {articulo}")
    print(f"   • Descripción: {descripcion_mostrar}")
    print(f"   • Categoría: {categoria}")
    print(f"   • Subcategoría: {subcategoria}")
    print(f"   • Estado actual: {status}")
    
    return {
        "ticket": id_ticket,
        "mensaje": "No se encontró plantilla adecuada - Ticket no asignado",
        "tipo": "No asignado",
        "texto_evaluado": {
            "origen": origen,
            "articulo": articulo,
            "descripcion_ticket": descripcion_mostrar,
            "categoria": categoria,
            "subcategoría": subcategoria
        },
        "razon_no_coincidencia": f"No se encontró plantilla activa que coincida con el contenido del ticket",
        "sugerencias": [
            "Verificar si el origen necesita una nueva plantilla",
            "Revisar si el artículo contiene palabras clave específicas",
            "Considerar actualizar la descripción de las plantillas existentes",
            "Crear nueva plantilla si es un caso recurrente"
        ],
        "procesado": False,
        "requiere_revision_manual": True,
        "fecha_procesamiento": datetime.now().isoformat()
    }


# ===================================================================
# ENDPOINTS MEJORADOS
# ===================================================================
@app.route("/procesar_ticket/<id_ticket>", methods=["GET"])
def procesar_ticket_endpoint(id_ticket):
    resultado = procesar_ticket_grupo(id_ticket)
    return jsonify(resultado)


@app.route("/procesar_todos", methods=["GET"])
def procesar_todos():
    print(f"\n{'='*100}")
    print(f"🔄 INICIANDO PROCESAMIENTO MASIVO DE TICKETS")
    print(f"   Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*100}")
    
    data = servicedesk_get("requests?status=Open")

    if "requests" not in data:
        error_msg = "No se pudieron obtener los tickets desde ServiceDesk"
        print(f"❌ {error_msg}")
        return jsonify({"error": error_msg})

    total_tickets = len(data["requests"])
    print(f"📊 Total de tickets abiertos encontrados: {total_tickets}")
    
    resultados = []
    asignados = 0
    no_asignados = 0
    no_procesados = 0
    
    for idx, req in enumerate(data["requests"], 1):
        id_ticket = req["id"]
        print(f"\n[{idx}/{total_tickets}] Procesando ticket #{id_ticket}")
        
        resultado = procesar_ticket_grupo(id_ticket)
        resultados.append(resultado)
        
        if resultado.get("procesado"):
            asignados += 1
        elif resultado.get("requiere_revision_manual"):
            no_asignados += 1
        else:
            no_procesados += 1
    
    print(f"\n{'='*100}")
    print(f"📊 RESUMEN FINAL DEL PROCESAMIENTO:")
    print(f"   Total tickets procesados: {total_tickets}")
    print(f"   Tickets asignados: {asignados}")
    print(f"   Tickets no asignados (requieren revisión): {no_asignados}")
    print(f"   Tickets no procesados (otras razones): {no_procesados}")
    print(f"   Tiempo total: {datetime.now().strftime('%H:%M:%S')}")
    print(f"{'='*100}")
    
    return jsonify({
        "resumen": {
            "total_tickets": total_tickets,
            "asignados": asignados,
            "no_asignados": no_asignados,
            "no_procesados": no_procesados,
            "fecha_procesamiento": datetime.now().isoformat()
        },
        "detalles": resultados
    })


@app.route("/verificar_plantillas/<path:origen>/<path:articulo>", methods=["GET"])
def verificar_plantillas(origen, articulo):
    """Endpoint para verificar manualmente coincidencias de plantillas"""
    print(f"\n{'='*100}")
    print(f"🔍 VERIFICACIÓN MANUAL DE PLANTILLAS")
    print(f"   Origen: {origen}")
    print(f"   Artículo: {articulo}")
    print(f"{'='*100}")
    
    match = match_template(origen, articulo, "")
    
    if match:
        return jsonify({
            "coincidencia_encontrada": True,
            "mensaje": "Se encontró plantilla que coincide",
            "texto_evaluado": {
                "origen": origen,
                "articulo": articulo
            },
            "plantilla_seleccionada": {
                "id": match['id'],
                "nombre": match['plantilla_incidente'],
                "origen": match.get('origen', 'No definido'),
                "articulo": match.get('articulo', ''),
                "descripcion": match.get('descripcion', 'Sin descripción')[:200],
                "categoria": match.get('categoria'),
                "subcategoria": match.get('subcategoria'),
                "grupo": match['grupo']
            },
            "razon_coincidencia": f"Coincidencia encontrada por origen y contenido",
            "informacion_adicional": {
                "campos_evaluados": ["articulo", "descripcion"],
                "logica": "Se evaluaron ambos campos 'articulo' y 'descripcion' de las plantillas contra el artículo del ticket"
            }
        })
    else:
        # Cargar todas las plantillas para mostrar qué se evaluó
        templates = load_templates()
        origenes_unicos = list(set([t['origen'] for t in templates if t.get('origen')]))
        
        return jsonify({
            "coincidencia_encontrada": False,
            "mensaje": "No se encontró plantilla que coincida",
            "texto_evaluado": {
                "origen": origen,
                "articulo": articulo
            },
            "informacion_plantillas": {
                "total_plantillas_activas": len(templates),
                "origenes_disponibles": origenes_unicos,
                "campos_evaluados": ["articulo", "descripcion"],
                "logica_evaluacion": "Se busca coincidencia en origen (si está definido), y luego en campos 'articulo' y 'descripcion' de la plantilla"
            },
            "razon_no_coincidencia": f"No hay plantilla activa que coincida con el origen '{origen}' y contenido proporcionado"
        })


# ===================================================================
# MAIN
# ===================================================================
if __name__ == "__main__":
    print(f"\n{'*'*100}")
    print(f"🚀 API DE ASIGNACIÓN AUTOMÁTICA INICIADA")
    print(f"   Servicio: Asignación por plantillas con status=1")
    print(f"   Lógica: Evalúa campos 'artículo' y 'descripción' de plantillas")
    print(f"   Peso: Artículo (3 puntos), Descripción (2 puntos)")
    print(f"   Manejo de NULL: Plantillas sin origen también se evalúan")
    print(f"   Hora de inicio: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'*'*100}\n")
    
    app.run(port=5000, debug=True)