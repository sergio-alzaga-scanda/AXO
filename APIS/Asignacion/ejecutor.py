import requests
import time
import logging
from datetime import datetime

# --- CONFIGURACIÓN DE URLS ---
URL_ESTADO = "http://158.23.137.150:8086/api_servicio.php"
URL_PROCESAR = "http://158.23.137.150:8087/procesar_todos_gpt"

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def verificar_estado_servicio():
    """
    Consulta si el servicio está activo (true) o inactivo (false)
    """
    try:
        logging.info(f"Verificando estado en {URL_ESTADO}...")
        response = requests.get(URL_ESTADO, timeout=10)
        response.raise_for_status()
        
        data = response.json()
        estado = data.get('activo', False)
        
        logging.info(f"Estado del servicio recibido: {'ACTIVO' if estado else 'INACTIVO'}")
        return estado

    except requests.exceptions.RequestException as e:
        logging.error(f"Error al verificar estado del servicio: {e}")
        return False
    except ValueError:
        logging.error("Error: La respuesta del estado no es un JSON válido")
        return False

def hacer_peticion():
    """
    Función para hacer la petición al endpoint de procesamiento
    """
    try:
        logging.info(f"🚀 Iniciando procesamiento en {URL_PROCESAR}")
        response = requests.get(URL_PROCESAR, timeout=300) # Timeout largo para procesos GPT
        
        # Verificar si la petición fue exitosa
        response.raise_for_status()
        
        logging.info(f"✅ Respuesta exitosa: {response.status_code}")
        if response.text:
            logging.info(f"Contenido: {response.text[:200]}...") 
        
        return True
        
    except requests.exceptions.ConnectionError:
        logging.error("Error de conexión con el procesador.")
    except requests.exceptions.Timeout:
        logging.error("Timeout en la petición de procesamiento.")
    except requests.exceptions.RequestException as e:
        logging.error(f"Error en la petición: {e}")
    except Exception as e:
        logging.error(f"Error inesperado: {e}")
    
    return False

def main():
    """
    Función principal
    """
    intervalo_minutos = 2
    intervalo_segundos = intervalo_minutos * 60 # Espera en segundos
    
    logging.info(f"Iniciando monitor. Verificación cada {intervalo_minutos} minutos")
    logging.info("Presiona Ctrl+C para detener el programa\n")
    
    contador = 0
    
    try:
        while True:
            contador += 1
            logging.info(f"--- Ciclo #{contador} ---")
            
            # 1. PRIMERO VERIFICAMOS EL ESTADO
            esta_activo = verificar_estado_servicio()
            
            if esta_activo:
                # 2. SOLO SI ESTÁ ACTIVO, PROCESAMOS
                hacer_peticion()
            else:
                # SI NO, ESPERAMOS
                logging.warning("⛔ El servicio está marcado como INACTIVO. No se ejecutará el proceso.")
            
            logging.info(f"Esperando {intervalo_minutos} minutos para el siguiente ciclo...")
            
            # Esperar el tiempo definido
            # Usamos time.sleep en bucle pequeño para responder rápido al Ctrl+C
            for _ in range(intervalo_segundos):
                time.sleep(1)
            
            print() # Separador visual

    except KeyboardInterrupt:
        logging.info("\n\nPrograma detenido por el usuario")
    except Exception as e:
        logging.error(f"Error inesperado en el programa principal: {e}")

if __name__ == "__main__":
    main()