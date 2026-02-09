import requests
import time
import logging
from datetime import datetime

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def hacer_peticion():
    """
    Función para hacer la petición al endpoint
    """
    url = "http://158.23.137.150:8087/procesar_todos_gpt"
    
    try:
        logging.info(f"Haciendo petición a {url}")
        response = requests.get(url)
        
        # Verificar si la petición fue exitosa
        response.raise_for_status()
        
        logging.info(f"Respuesta exitosa: {response.status_code}")
        if response.text:
            logging.info(f"Contenido: {response.text[:200]}...")  # Mostrar primeros 200 caracteres
        
        return True
        
    except requests.exceptions.ConnectionError:
        logging.error("Error de conexión. Verifica que el servidor esté ejecutándose.")
    except requests.exceptions.Timeout:
        logging.error("Timeout en la petición.")
    except requests.exceptions.RequestException as e:
        logging.error(f"Error en la petición: {e}")
    except Exception as e:
        logging.error(f"Error inesperado: {e}")
    
    return False

def main():
    """
    Función principal que ejecuta la petición cada 5 minutos
    """
    intervalo_minutos = 2
    intervalo_segundos = intervalo_minutos * 60
    
    logging.info(f"Iniciando programa. Peticiones cada {intervalo_minutos} minutos")
    logging.info(f"Endpoint: http://127.0.0.1:5000/procesar_todos")
    logging.info("Presiona Ctrl+C para detener el programa\n")
    
    contador = 0
    
    try:
        while True:
            contador += 1
            logging.info(f"--- Petición #{contador} ---")
            
            # Hacer la petición
            exito = hacer_peticion()
            
            if exito:
                logging.info("Petición completada con éxito")
            else:
                logging.warning("Petición fallida")
            
            logging.info(f"Esperando {intervalo_minutos} minutos para la próxima petición...")
            
            # Esperar 5 minutos (300 segundos)
            # Usamos un bucle para poder interrumpir la espera con Ctrl+C
            for _ in range(intervalo_segundos):
                time.sleep(1)
            
            print()  # Línea en blanco para separar las ejecuciones
            
    except KeyboardInterrupt:
        logging.info("\n\nPrograma detenido por el usuario")
        logging.info(f"Total de peticiones realizadas: {contador}")
    except Exception as e:
        logging.error(f"Error inesperado en el programa principal: {e}")

if __name__ == "__main__":
    # Versión alternativa con verificación inicial
    print("=" * 60)
    print("PROGRAMA DE PETICIONES PERIÓDICAS")
    print("=" * 60)
    
    # Verificar si podemos conectar al endpoint antes de empezar
    url_test = "http://127.0.0.1:5000/"
    try:
        test_response = requests.get(url_test, timeout=5)
        print(f"✓ Servidor detectado en {url_test}")
        print(f"  Estado: {test_response.status_code}")
    except:
        print(f"⚠ No se pudo conectar a {url_test}")
        print("  Asegúrate de que el servidor esté ejecutándose")
    
    print("\n" + "=" * 60)
    
    # Preguntar al usuario si quiere continuar
    respuesta = input("¿Deseas iniciar el programa? (s/n): ")
    
    if respuesta.lower() == 's':
        main()
    else:
        print("Programa cancelado")