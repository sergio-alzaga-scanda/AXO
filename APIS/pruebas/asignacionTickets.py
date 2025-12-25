import os
import json
import requests
from flask import Flask, jsonify, request
from dotenv import load_dotenv

# ============================
# CONFIG
# ============================
load_dotenv()

API_KEY = os.getenv("API_KEY", "5E1B42AC-D6EE-4D82-850C-9AD9CE3C7C2D")
VERIFY_SSL = os.getenv("VERIFY_SSL", "false").lower() == "true"
BASE_URL = os.getenv("BASE_URL", "https://servicedesk.grupoaxo.com/api/v3/requests")

# Grupo por defecto si no se encuentra palabra clave
GRUPO_DEFAULT = "Aprobaciones Help Desk"

# Palabras clave a grupo (puedes expandir)
KEYWORDS_GRUPOS = {
    "hardware": "Hardware Problems",
    "software": "Software Issues",
    "red": "Network Issues",
}

# Lista de técnicos activos (simulado)
TECNICOS = [
    {"nombre": "Charles", "correo": "charles@zmail.com"}
]

app = Flask(__name__)

# ============================
# UTILIDADES
# ============================

def api_headers():
    return {
        "Authtoken": API_KEY,
        "Accept": "application/vnd.manageengine.sdp.v3+json",
        "Content-Type": "application/x-www-form-urlencoded"
    }

def obtener_tickets_abiertos():
    try:
        r = requests.get(BASE_URL, headers=api_headers(), verify=VERIFY_SSL)
        if r.ok:
            tickets = r.json().get("requests", [])
            # Solo tickets sin técnico asignado
            return [t for t in tickets if not t.get("technician")]
        return []
    except Exception as e:
        print(f"[ERROR] obtener_tickets_abiertos: {e}")
        return []

def obtener_ticket(ticket_id):
    try:
        r = requests.get(f"{BASE_URL}/{ticket_id}", headers=api_headers(), verify=VERIFY_SSL)
        if r.ok:
            return r.json().get("request")
        return None
    except Exception as e:
        print(f"[ERROR] obtener_ticket: {e}")
        return None

def asignar_ticket(ticket, tecnico):
    ticket_id = ticket.get("id")
    descripcion = (ticket.get("subject") or ticket.get("short_description") or "").lower()

    # Determinar grupo por palabra clave
    grupo_asignado = GRUPO_DEFAULT
    for k, g in KEYWORDS_GRUPOS.items():
        if k.lower() in descripcion:
            grupo_asignado = g
            break

    payload = {
        "request": {
            "group": {"name": grupo_asignado},
            "technician": {"email_id": tecnico["correo"]}
        }
    }

    try:
        r = requests.put(
            f"{BASE_URL}/{ticket_id}",
            headers=api_headers(),
            data={"input_data": json.dumps(payload)},
            verify=VERIFY_SSL
        )
        if r.ok:
            print(f"[SUCCESS] Ticket {ticket_id} asignado a {tecnico['nombre']} en grupo {grupo_asignado}")
            return {"ticket_id": ticket_id, "asignado_a": tecnico["nombre"], "grupo": grupo_asignado, "status": "ok"}
        else:
            print(f"[ERROR] Ticket {ticket_id} no actualizado: {r.status_code}")
            return {"ticket_id": ticket_id, "status": "error", "detalle": r.text}
    except Exception as e:
        return {"ticket_id": ticket_id, "status": "error", "detalle": str(e)}

# ============================
# ENDPOINT
# ============================

@app.route("/asignar_tickets", methods=["POST"])
def asignar_tickets():
    data = request.get_json(silent=True) or {}
    ticket_id = data.get("id")

    tecnico = TECNICOS[0]  # Solo un técnico para simplificar

    if ticket_id:
        ticket = obtener_ticket(ticket_id)
        if not ticket:
            return jsonify({"error": "Ticket no encontrado"}), 404
        resultado = asignar_ticket(ticket, tecnico)
        return jsonify(resultado)

    # Si no se envía id_ticket → todos los tickets abiertos
    tickets = obtener_tickets_abiertos()
    resultados = [asignar_ticket(t, tecnico) for t in tickets]
    return jsonify({"total": len(tickets), "resultados": resultados})

# ============================
# HEALTH CHECK
# ============================

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "asignador_tickets"})

# ============================
# RUN
# ============================

if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=5000)
