from flask import Flask, jsonify, request
import requests

app = Flask(__name__)

# =======================
# CONFIGURACIÓN DE SERVICEDESK
# =======================
BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/requests"
API_KEY = "5E1B42AC-D6EE-4D82-850C-9AD9CE3C7C2D"

# =======================
# FUNCIÓN: OBTENER TICKETS ABIERTOS O UNO POR ID
# =======================
def obtener_tickets_abiertos(ticket_id=None):
    headers = {
        "Authtoken": API_KEY,
        "Accept": "application/vnd.manageengine.sdp.v3+json"
    }

    try:
        # ===========================================
        # 1) Buscar por ID EXACTO
        # ===========================================
        if ticket_id and ticket_id.isdigit():
            url = f"{BASE_URL}/{ticket_id}"
            respuesta = requests.get(url, headers=headers, verify=False)

            # Si el ticket no existe, no hacemos raise para poder devolver []
            if respuesta.status_code == 404:
                return []

            respuesta.raise_for_status()
            data = respuesta.json()
            ticket = data.get("request") or data
            return [ticket] if ticket else []

        # ===========================================
        # 2) Buscar por ID PARCIAL (ej: "12" → "1203", "9125"...)
        # ===========================================
        if ticket_id:
            respuesta = requests.get(BASE_URL, headers=headers, verify=False)
            respuesta.raise_for_status()
            tickets = respuesta.json().get("requests", [])

            # Filtrar los que contengan el patrón
            filtrados = [t for t in tickets if ticket_id in str(t.get("id", ""))]

            return filtrados

        # ===========================================
        # 3) Si no manda ID → devolver tickets abiertos
        # ===========================================
        respuesta = requests.get(BASE_URL, headers=headers, verify=False)
        respuesta.raise_for_status()
        tickets = respuesta.json().get("requests", [])
        abiertos = [t for t in tickets if not t.get("technician")]
        return abiertos

    except Exception as e:
        print("❌ Error al obtener tickets:", e)
        return []
# =======================
# FUNCIÓN: RESUMIR TICKETS
# =======================
def resumir_tickets(tickets):
    resumen = []
    for t in tickets:
        if not isinstance(t, dict):
            continue

        resumen.append({
            "id": t.get("id"),
            "asunto": t.get("subject"),
            "descripcion_corta": (t.get("short_description") or "")[:200] + "...",
            "solicitante": t.get("requester", {}).get("name"),
            "correo": t.get("requester", {}).get("email_id"),
            "departamento": (
                t.get("requester", {}).get("department", {}).get("name")
                if isinstance(t.get("requester", {}).get("department"), dict)
                else None
            ),
            "prioridad": (
                t.get("priority", {}).get("name")
                if isinstance(t.get("priority"), dict)
                else None
            ),
            "estado": (
                t.get("status", {}).get("name")
                if isinstance(t.get("status"), dict)
                else None
            ),
            "fecha_creacion": t.get("created_time", {}).get("display_value"),
            "fecha_vencimiento": t.get("due_by_time", {}).get("display_value"),
            "sitio": (
                t.get("site", {}).get("name")
                if isinstance(t.get("site"), dict)
                else None
            ),

            # Campos adicionales solicitados
            "udf_pick_27": (
                t.get("udf_fields", {}).get("udf_pick_27")
                if isinstance(t.get("udf_fields"), dict)
                else None
            ),
            "mode": (
                t.get("mode", {}).get("name")
                if isinstance(t.get("mode"), dict)
                else t.get("mode")
            ),
            "udf_pick_2114": (
                t.get("udf_fields", {}).get("udf_pick_2114")
                if isinstance(t.get("udf_fields"), dict)
                else None
            ),
            "item": (
                t.get("item", {}).get("name")
                if isinstance(t.get("item"), dict)
                else t.get("item")
            ),
            "category": (
                t.get("category", {}).get("name")
                if isinstance(t.get("category"), dict)
                else t.get("category")
            ),
            "subcategory": (
                t.get("subcategory", {}).get("name")
                if isinstance(t.get("subcategory"), dict)
                else t.get("subcategory")
            )
        })
    return resumen

# =======================
# ENDPOINT PRINCIPAL
# =======================
@app.route("/tickets-abiertos", methods=["GET"])
def mostrar_tickets_abiertos():
    tipo = request.args.get("tipo", default="2", type=str)  # 1 = completo, 2 = resumen
    ticket_id = request.args.get("ticket_id", default=None, type=str)  # <-- CORREGIDO

    tickets = obtener_tickets_abiertos(ticket_id)

    if not tickets:
        return jsonify({
            "mensaje": "Ticket no encontrado" if ticket_id else "No hay tickets abiertos",
            "total_tickets": 0,
            "tickets": []
        }), 404

    if tipo == "1":
        resultado = tickets
    else:
        resultado = resumir_tickets(tickets)

    return jsonify({
        "total_tickets": len(tickets),
        "tipo_mostrado": "completo" if tipo == "1" else "resumen",
        "filtro_id": ticket_id,
        "tickets": resultado
    })


# =======================
# ENDPOINT OPCIONAL RESTFUL /tickets-abiertos/<id>
# =======================
@app.route("/tickets-abiertos/<ticket_id>", methods=["GET"])
def mostrar_ticket_por_id(ticket_id):
    tipo = request.args.get("tipo", default="2", type=str)
    tickets = obtener_tickets_abiertos(ticket_id)

    if not tickets:
        return jsonify({
            "mensaje": "Ticket no encontrado",
            "total_tickets": 0,
            "tickets": []
        }), 404

    if tipo == "1":
        resultado = tickets
    else:
        resultado = resumir_tickets(tickets)

    return jsonify({
        "total_tickets": len(tickets),
        "tipo_mostrado": "completo" if tipo == "1" else "resumen",
        "filtro_id": ticket_id,
        "tickets": resultado
    })

# =======================
# INICIALIZACIÓN DEL SERVIDOR
# =======================
if __name__ == "__main__":
    app.run(debug=True)
