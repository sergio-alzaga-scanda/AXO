import requests
import urllib3

urllib3.disable_warnings()

API_KEY = "5E1B42AC-D6EE-4D82-850C-9AD9CE3C7C2D"

url = "https://servicedesk.grupoaxo.com/api/v3/lookup?type=groups"

headers = {
    "Authtoken": API_KEY,
    "Accept": "application/vnd.manageengine.sdp.v3+json"
}

resp = requests.get(url, headers=headers, verify=False)

print("STATUS:", resp.status_code)
print("RAW RESPONSE:")
print(resp.text)

try:
    print("\nJSON PARSED:")
    print(resp.json())
except:
    print("\n⚠ NO ES JSON — PROBABLEMENTE TOKEN, PORTAL O ENDPOINT INCORRECTO")
