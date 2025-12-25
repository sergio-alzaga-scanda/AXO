import requests
import re
import unicodedata
from config import BASE_URL, API_KEY

def strip_accents(text):
    if not text:
        return ""
    return ''.join(
        c for c in unicodedata.normalize('NFD', text)
        if unicodedata.category(c) != 'Mn'
    )

def servicedesk_get(endpoint, params=None):
    headers = {"authtoken": API_KEY}
    url = f"{BASE_URL}/{endpoint}"
    response = requests.get(url, headers=headers, params=params, verify=False)
    return response.json()

def servicedesk_put(endpoint, data):
    headers = {"authtoken": API_KEY}
    url = f"{BASE_URL}/{endpoint}"
    req = {"input_data": data}
    response = requests.put(url, headers=headers, data=req, verify=False)
    return response.json()

def get_ticket(id_ticket):
    return servicedesk_get(f"requests/{id_ticket}")

def get_open_tickets():
    params = {
        "input_data": """{
            "list_info": {
                "row_count": "200",
                "start_index": "1",
                "sort_field": "created_time",
                "sort_order": "desc"
            }
        }"""
    }
    data = servicedesk_get("requests", params)

    result = []
    if "requests" in data:
        for r in data["requests"]:
            estado = r.get("status", {}).get("name", "").lower()
            if estado in ["abierta", "no asignado", "no asignada"]:
                result.append(r["id"])

    return result

def get_template(template_id):
    return servicedesk_get(f"request_templates/{template_id}")
