import requests
import time
import tkinter as tk
from tkinter import font
import threading
import winsound  # Solo funciona en Windows

# --- CONFIGURACIÓN FIVE9 ---

USER = "ricardo.lopez@scanda.com.mx"
PASS = "S4c4nd4_rlc_27"
# Ejemplo de endpoint (varía según tu dominio: app.five9.com, app.five9.eu, etc.)
API_URL = "https://app.five9.com/appsvcs/rs/svc/agents/5346492/state"

alerta_activa = False

def obtener_estado_five9():
    """
    Consulta la API de Five9 para ver el estado del agente.
    Retorna el string del estado (ej: 'RINGING', 'READY', 'NOT_READY').
    """
    try:
        # En entornos reales de Five9, a veces requieres tokens en los headers
        # en lugar de Auth básica directa en cada call.
        response = requests.get(API_URL, auth=(USER, PASS), timeout=5)
        
        if response.status_code == 200:
            data = response.json()
            # Ajusta la clave según el JSON real de retorno de Five9
            return data.get('state', 'UNKNOWN') 
        else:
            print(f"Error API: {response.status_code}")
            return "ERROR"
    except Exception as e:
        print(f"Excepción de conexión: {e}")
        return "ERROR"

def mostrar_alerta_intrusiva():
    """
    Crea una ventana 'Always on Top' que roba el foco.
    """
    global alerta_activa
    alerta_activa = True
    
    # Configuración de la ventana
    root = tk.Tk()
    root.title("¡LLAMADA ENTRANTE!")
    root.configure(bg='red')
    
    # Dimensiones y posición (Centrado o esquina)
    width = 400
    height = 200
    screen_width = root.winfo_screenwidth()
    screen_height = root.winfo_screenheight()
    x = (screen_width // 2) - (width // 2)
    y = (screen_height // 2) - (height // 2)
    root.geometry(f'{width}x{height}+{x}+{y}')
    
    # PROPIEDAD CLAVE: SIEMPRE VISIBLE
    root.attributes('-topmost', True) 
    
    # Etiqueta de texto
    lbl_font = font.Font(family='Helvetica', size=20, weight='bold')
    label = tk.Label(root, text="📞 LLAMADA FIVE9 📞", bg='red', fg='white', font=lbl_font)
    label.pack(expand=True)
    
    # Botón para cerrar
    btn = tk.Button(root, text="Entendido", command=root.destroy, font=('Helvetica', 12))
    btn.pack(pady=20)
    
    # Sonido del sistema (Loop simple)
    def play_sound():
        try:
            winsound.Beep(1000, 500) # 1000Hz por 500ms
            winsound.Beep(1500, 500)
        except:
            pass
    
    root.after(100, play_sound)
    
    root.mainloop()
    
    alerta_activa = False

def main_loop():
    print("Iniciando monitor de Five9...")
    while True:
        if not alerta_activa:
            estado = obtener_estado_five9()
            print(f"Estado actual: {estado}") # Debug
            
            # Ajusta "RINGING" al string exacto que devuelva tu API
            if estado == "RINGING" or estado == "OFFERING":
                print("¡Llamada detectada!")
                # Lanzamos la alerta en el hilo principal o bloqueamos hasta cerrar
                # Aquí bloqueamos el loop hasta que cierres la ventana para no spammear
                mostrar_alerta_intrusiva()
        
        # Esperar 2 segundos antes del siguiente chequeo
        time.sleep(2)

if __name__ == "__main__":
    main_loop()