import requests
import json
from flask import Flask, request, jsonify

app = Flask(__name__)

# --- CONFIGURACIÓN ---
BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/requests"
API_KEY = "5E1B42AC-D6EE-4D82-850C-9AD9CE3C7C2D"


@app.route('/api/v1/request/update', methods=['POST'])
def update_request():
    """
    Endpoint para actualizar el grupo y técnico de un request en ManageEngine.
    Usa el método PUT y el campo 'input_data'.
    """
    data = request.get_json()

    if not data:
        return jsonify({"error": "Falta el cuerpo JSON de la solicitud."}), 400

    request_id = data.get('request_id')
    group_name = data.get('group_name')
    technician_name = data.get('technician_name')

    if not all([request_id, group_name, technician_name]):
        return jsonify({
            "error": "Faltan parámetros.",
            "requeridos": ["request_id", "group_name", "technician_name"]
        }), 400

    # 1. Construir el JSON Payload
    # INCLUYE AHORA TODOS LOS CAMPOS OBLIGATORIOS (fields listados en el error 4012)
    sdp_payload = {
        "request": {
            # --- CAMPOS OBLIGATORIOS REQUERIDOS POR SDP ---
            # ¡ADVERTENCIA! Reemplaza los valores de ejemplo con valores VALIDOS en tu SDP
            "mode": {"name": "E-Mail"}, 
            "category": {"name": "Incidente"}, 
            "subcategory": {"name": "Acceso"}, 
            "item": {"name": "VPN"}, 
            "site": {"name": "Sede Central"},
            
            # Campos Personalizados (UDF) - ¡Asegúrate que los valores son válidos!
            # "udf_pick_27": "Valor A", 
            # "udf_pick_2114": "Opción B", 
            
            # --- CAMPOS DE ACTUALIZACIÓN (Objetivo) ---
            "group": {
                "name": group_name
            },
            "technician": {
                "email_id": technician_name
            }
        }
    }

    # 2. Serializar el payload a una cadena JSON
    sdp_payload_string = json.dumps(sdp_payload)
    
    # 3. Crear la estructura final requerida: {'input_data': '{"request": ...}'}
    data_to_send = {
        "input_data": sdp_payload_string
    }

    # 4. Construir la URL y los Headers
    url = f"{BASE_URL}/{request_id}"
    
    headers = {
        # Usamos el formato que has probado recientemente
        "Authtoken": API_KEY,
        "Accept": "application/vnd.manageengine.sdp.v3+json"
    }

    # 5. Realizar la llamada PUT a ManageEngine
    try:
        response = requests.put(url, data=data_to_send, headers=headers)
        response.raise_for_status()

        # 6. Devolver la respuesta de ManageEngine al cliente
        return jsonify(response.json()), response.status_code

    except requests.exceptions.HTTPError as e:
        try:
            error_detail = e.response.json()
        except json.JSONDecodeError:
            error_detail = e.response.text

        return jsonify({
            "error": "Error al comunicarse con ManageEngine (HTTP)",
            "status_code": e.response.status_code,
            "detail": error_detail
        }), e.response.status_code
        
    except requests.exceptions.RequestException as e:
        return jsonify({
            "error": "Error de conexión o de red (RequestException)",
            "detail": str(e)
        }), 500


if __name__ == '__main__':
    app.run(debug=True, port=5000)