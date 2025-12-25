from flask import Flask, request, jsonify
import json
import requests

app = Flask(__name__)

BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/requests"
API_KEY = "5E1B42AC-D6EE-4D82-850C-9AD9CE3C7C2D"

# Template requirements aprendidos dinámicamente
TEMPLATE_REQUIREMENTS = {}

def get_headers():
    return {
        "Accept": "application/vnd.manageengine.sdp.v3+json",
        "Authtoken": API_KEY,
        "Content-Type": "application/x-www-form-urlencoded",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    }

def make_service_desk_request(method, url_suffix="", data=None):
    url = f"{BASE_URL}{url_suffix}"
    headers = get_headers()
    
    try:
        if method.upper() == "GET":
            response = requests.get(url, headers=headers, timeout=30)
        elif method.upper() == "POST":
            response = requests.post(url, headers=headers, data=data, timeout=30)
        elif method.upper() == "PUT":
            response = requests.put(url, headers=headers, data=data, timeout=30)
        else:
            return {"success": False, "error": f"Método no soportado: {method}"}
        
        print(f"Status Code: {response.status_code}")
        return {
            "success": 200 <= response.status_code < 300,
            "status_code": response.status_code,
            "data": response.json() if response.content else {},
            "text": response.text
        }
        
    except requests.exceptions.RequestException as e:
        return {
            "success": False,
            "error": f"Error de conexión: {str(e)}"
        }

def extract_fields_from_error(error_text):
    """Extrae campos requeridos del mensaje de error"""
    try:
        error_data = json.loads(error_text)
        messages = error_data.get("response_status", {}).get("messages", [])
        
        for message in messages:
            if message.get("type") == "failed":
                fields = message.get("fields", [])
                if fields:
                    print(f"🎯 Campos requeridos detectados: {fields}")
                    return fields
    except Exception as e:
        print(f"Error al analizar mensaje: {e}")
    
    return []

def build_smart_payload(base_data, required_fields):
    """Construye payload con valores inteligentes"""
    
    payload = {
        "request": {
            "technician": {"email_id": base_data['technician_email']},
            "group": {"name": base_data['group_name']},
            "template": {"name": base_data['template_name']}
        }
    }
    
    # field_values = {
    #     "item": "General",
    #     "category": NULL,
    #     "subcategory": NULL, 
    #     "udf_pick_27": "Sí",
    #     "udf_pick_2114": "Normal"
    # }
    
    for field in required_fields:
        if field in ["item", "category", "subcategory", "mode"]:
            payload["request"][field] = {"name": field_values.get(field, "General")}
            print(f"  ✅ {field}: {field_values.get(field, 'General')}")
        
        elif field.startswith("udf_"):
            if "udf_fields" not in payload["request"]:
                payload["request"]["udf_fields"] = {}
            payload["request"]["udf_fields"][field] = field_values.get(field, "Sí")
            print(f"  ✅ {field}: {field_values.get(field, 'Sí')}")
    
    return payload

@app.route('/update_request_auto', methods=['POST'])
def update_request_auto():
    """Endpoint automático mejorado que aprende en tiempo real"""
    if not request.is_json:
        return jsonify({"success": False, "error": "Content-Type debe ser application/json"}), 415
    
    data = request.get_json()
    
    required_fields = ['request_id', 'technician_email', 'group_name', 'template_name']
    missing_fields = [field for field in required_fields if field not in data]
    
    if missing_fields:
        return jsonify({
            "success": False,
            "error": f"Campos faltantes: {', '.join(missing_fields)}"
        }), 400

    template_name = data['template_name']
    request_id = data['request_id']
    
    print(f"🚀 ACTUALIZACIÓN AUTOMÁTICA - Request: {request_id}, Template: {template_name}")
    
    # PRIMERO: Verificar si ya conocemos los requisitos de este template
    known_requirements = TEMPLATE_REQUIREMENTS.get(template_name, [])
    
    if known_requirements:
        print(f"📋 Usando requisitos conocidos: {known_requirements}")
        payload = build_smart_payload(data, known_requirements)
        
        print("📦 Payload con campos conocidos:")
        print(json.dumps(payload, indent=2, ensure_ascii=False))
        
        post_data = f"input_data={json.dumps(payload)}"
        result = make_service_desk_request("PUT", f"/{request_id}", post_data)
        
        if result["success"]:
            print("✅ ÉXITO con campos conocidos")
            return jsonify({
                "success": True,
                "message": f"Request {request_id} actualizado",
                "request_id": request_id,
                "template": template_name,
                "method": "known_requirements"
            }), 200
    
    # SEGUNDO: Si no conocemos los requisitos o falló, probar con payload básico
    print("🔄 Probando con payload básico...")
    basic_payload = {
        "request": {
            "technician": {"email_id": data['technician_email']},
            "group": {"name": data['group_name']},
            "template": {"name": template_name}
        }
    }
    
    post_data = f"input_data={json.dumps(basic_payload)}"
    result = make_service_desk_request("PUT", f"/{request_id}", post_data)
    
    if result["success"]:
        print("✅ ÉXITO con payload básico")
        return jsonify({
            "success": True,
            "message": f"Request {request_id} actualizado",
            "request_id": request_id,
            "template": template_name,
            "method": "basic_payload"
        }), 200
    
    # TERCERO: Aprender del error y reintentar
    print("🎯 Aprendiendo del error...")
    error_text = result.get("text", "")
    detected_fields = extract_fields_from_error(error_text)
    
    if detected_fields:
        print(f"💡 Aprendidos nuevos campos para '{template_name}': {detected_fields}")
        
        # Guardar en memoria los requisitos aprendidos
        TEMPLATE_REQUIREMENTS[template_name] = detected_fields
        
        # Construir payload con los campos aprendidos
        learned_payload = build_smart_payload(data, detected_fields)
        
        print("📦 Payload con campos aprendidos:")
        print(json.dumps(learned_payload, indent=2, ensure_ascii=False))
        
        post_data = f"input_data={json.dumps(learned_payload)}"
        result = make_service_desk_request("PUT", f"/{request_id}", post_data)
        
        if result["success"]:
            print("✅ ÉXITO después de aprender")
            return jsonify({
                "success": True,
                "message": f"Request {request_id} actualizado después de aprender",
                "request_id": request_id,
                "template": template_name,
                "method": "learned_from_error",
                "learned_fields": detected_fields
            }), 200
    
    # SI TODO FALLA
    print("❌ Todos los intentos fallaron")
    return jsonify({
        "success": False,
        "error": f"Error {result['status_code']}",
        "details": error_text,
        "detected_fields": detected_fields,
        "template": template_name,
        "suggestion": f"Template '{template_name}' requiere campos específicos"
    }), result['status_code']

@app.route('/update_request_forced', methods=['POST'])
def update_request_forced():
    """Endpoint que fuerza el envío de campos específicos"""
    if not request.is_json:
        return jsonify({"success": False, "error": "Content-Type debe ser application/json"}), 415
    
    data = request.get_json()
    
    required_fields = ['request_id', 'technician_email', 'group_name', 'template_name']
    missing_fields = [field for field in required_fields if field not in data]
    
    if missing_fields:
        return jsonify({
            "success": False,
            "error": f"Campos faltantes: {', '.join(missing_fields)}"
        }), 400

    # Campos específicos para Default Request basados en el error
    forced_fields = ["item", "category", "subcategory", "mode", "udf_pick_27", "udf_pick_2114"]
    
    payload = {
        "request": {
            "technician": {"email_id": data['technician_email']},
            "group": {"name": data['group_name']},
            "template": {"name": data['template_name']},
            "item": {"name": "General"},
            "category": {"name": NULL},
            "subcategory": {"name": NULL},
            
            "udf_fields": {
                "udf_pick_27": "Sí",
                "udf_pick_2114": "Normal"
            }
        }
    }
    
    print("🔧 ENVIANDO PAYLOAD FORZADO:")
    print(json.dumps(payload, indent=2, ensure_ascii=False))
    
    post_data = f"input_data={json.dumps(payload)}"
    result = make_service_desk_request("PUT", f"/{data['request_id']}", post_data)
    
    if result["success"]:
        return jsonify({
            "success": True,
            "message": f"Request {data['request_id']} actualizado forzadamente",
            "request_id": data['request_id'],
            "template": data['template_name'],
            "method": "forced_payload"
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']

@app.route('/get_learned_templates', methods=['GET'])
def get_learned_templates():
    """Muestra los templates que el sistema ha aprendido"""
    return jsonify({
        "success": True,
        "learned_templates": TEMPLATE_REQUIREMENTS
    }), 200

@app.route('/clear_learned_templates', methods=['POST'])
def clear_learned_templates():
    """Limpia la memoria de templates aprendidos"""
    global TEMPLATE_REQUIREMENTS
    TEMPLATE_REQUIREMENTS = {}
    return jsonify({
        "success": True,
        "message": "Memoria de templates limpiada"
    }), 200

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        "status": "healthy",
        "learned_templates_count": len(TEMPLATE_REQUIREMENTS),
        "learned_templates": list(TEMPLATE_REQUIREMENTS.keys())
    }), 200


def build_smart_payload(base_data, required_fields):
    """Construye payload con valores inteligentes"""
    
    payload = {
        "request": {
            "technician": {"email_id": base_data['technician_email']},
            "group": {"name": base_data['group_name']},
            "template": {"name": base_data['template_name']}
        }
    }
    
    # Valores inteligentes por tipo de campo - CORREGIDO: usar None en lugar de NULL
    field_values = {
        "item": "General",
        "category": None,  # CORREGIDO
        "subcategory": None,  # CORREGIDO
        "udf_pick_27": "Sí",
        "udf_pick_2114": "Normal"
    }
    
    for field in required_fields:
        if field in ["item", "category", "subcategory", "mode"]:
            payload["request"][field] = {"name": field_values.get(field, "General")}
            print(f"  ✅ {field}: {field_values.get(field, 'General')}")
        
        elif field.startswith("udf_"):
            if "udf_fields" not in payload["request"]:
                payload["request"]["udf_fields"] = {}
            payload["request"]["udf_fields"][field] = field_values.get(field, "Sí")
            print(f"  ✅ {field}: {field_values.get(field, 'Sí')}")
    
    return payload
    
@app.route('/get_request/<request_id>', methods=['GET'])
def get_request(request_id):
    """Endpoint para obtener información de una solicitud"""
    result = make_service_desk_request("GET", f"/{request_id}")
    
    if result.get("cloudflare_block"):
        return jsonify({
            "success": False,
            "error": "Acceso bloqueado por Cloudflare"
        }), 403
    
    if result["success"]:
        return jsonify({
            "success": True,
            "request": result["data"]
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']
    

@app.route('/get_request_details/<request_id>', methods=['GET'])
def get_request_details(request_id):
    result = make_service_desk_request("GET", f"/{request_id}")
    
    if result["success"]:
        return jsonify({
            "success": True,
            "request": result["data"]
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']


@app.route('/get_template_fields/<template_name>', methods=['GET'])
def get_template_fields(template_name):
    """Obtener requests existentes con un template específico para ver campos requeridos"""
    result = make_service_desk_request("GET", "")
    
    if result["success"]:
        requests_data = result["data"].get("requests", [])
        template_requests = []
        
        for req in requests_data:
            if (req.get('template') and 
                isinstance(req['template'], dict) and 
                req['template'].get('name') == template_name):
                
                # Extraer campos relevantes - CORREGIDO: usar None en lugar de NULL
                template_info = {
                    'id': req.get('id'),
                    'subject': req.get('subject'),
                    'item': req.get('item'),
                    'category': req.get('category'),  # CORREGIDO
                    'subcategory': req.get('subcategory'),  # CORREGIDO
                    'udf_fields': req.get('udf_fields', {})
                }
                template_requests.append(template_info)
        
        return jsonify({
            "success": True,
            "template": template_name,
            "sample_requests": template_requests,
            "total_found": len(template_requests)
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']

@app.route('/get_templates', methods=['GET'])
def get_templates():
    result = make_service_desk_request("GET", "")
    
    if result["success"]:
        requests_data = result["data"].get("requests", [])
        templates = set()
        
        for req in requests_data:
            # Manejar valores nulos correctamente
            if (req.get('template') and 
                isinstance(req['template'], dict) and 
                req['template'].get('name')):
                templates.add(req['template']['name'])
        
        return jsonify({
            "success": True,
            "available_templates": list(templates),
            "total_requests": len(requests_data)
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']

@app.route('/get_groups', methods=['GET'])
def get_groups():
    """Obtener todos los grupos disponibles - VERSIÓN CORREGIDA"""
    result = make_service_desk_request("GET", "")
    
    if result["success"]:
        requests_data = result["data"].get("requests", [])
        groups = set()
        
        for req in requests_data:
            # Manejar valores nulos correctamente
            if (req.get('group') and 
                isinstance(req['group'], dict) and 
                req['group'].get('name')):
                groups.add(req['group']['name'])
        
        return jsonify({
            "success": True,
            "available_groups": list(groups)
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']


@app.route('/get_groups_detailed', methods=['GET'])
def get_groups_detailed():
    """Obtener grupos con más detalles"""
    result = make_service_desk_request("GET", "")
    
    if result["success"]:
        requests_data = result["data"].get("requests", [])
        groups = {}
        
        for req in requests_data:
            if (req.get('group') and 
                isinstance(req['group'], dict) and 
                req['group'].get('name')):
                group_name = req['group']['name']
                if group_name not in groups:
                    groups[group_name] = {
                        'name': group_name,
                        'technicians': set(),
                        'templates': set()
                    }
                
                # Agregar técnico si existe
                if (req.get('technician') and 
                    isinstance(req['technician'], dict) and 
                    req['technician'].get('email_id')):
                    groups[group_name]['technicians'].add(req['technician']['email_id'])
                
                # Agregar template si existe
                if (req.get('template') and 
                    isinstance(req['template'], dict) and 
                    req['template'].get('name')):
                    groups[group_name]['templates'].add(req['template']['name'])
        
        # Convertir sets a listas para JSON
        for group in groups.values():
            group['technicians'] = list(group['technicians'])
            group['templates'] = list(group['templates'])
        
        return jsonify({
            "success": True,
            "groups": groups
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']

@app.route('/get_technicians', methods=['GET'])
def get_technicians():
    """Obtener todos los técnicos disponibles - VERSIÓN CORREGIDA"""
    result = make_service_desk_request("GET", "")
    
    if result["success"]:
        requests_data = result["data"].get("requests", [])
        technicians = set()
        
        for req in requests_data:
            # Manejar valores nulos correctamente
            if (req.get('technician') and 
                isinstance(req['technician'], dict) and 
                req['technician'].get('email_id')):
                technicians.add(req['technician']['email_id'])
        
        return jsonify({
            "success": True,
            "available_technicians": list(technicians)
        }), 200
    else:
        return jsonify({
            "success": False,
            "error": f"Error {result['status_code']}",
            "details": result.get("text", "")
        }), result['status_code']


if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)