import re

def main():
    with open('c:/wamp64/www/AXO/APIS/Asignacion/app.py', 'r', encoding='utf-8') as f:
        content = f.read()

    if 'import pytz' not in content:
        content = content.replace("import os", "import os\nimport pytz")
        
    helper_func = """
def obtener_datetime_cdmx():
    \"\"\"Retorna el datetime actual en la zona horaria de Ciudad de México sin tzinfo.\"\"\"
    tz_cdmx = pytz.timezone('America/Mexico_City')
    return datetime.now(tz_cdmx).replace(tzinfo=None)
"""
    if "def obtener_datetime_cdmx" not in content:
        content = content.replace("app = Flask(__name__)", f"app = Flask(__name__)\n{helper_func}")

    lines = content.split('\n')
    for i, line in enumerate(lines):
        if "def obtener_datetime_cdmx" in line or "tz_cdmx" in line or "datetime.now(tz_cdmx)" in line:
            continue
        lines[i] = lines[i].replace("datetime.now()", "obtener_datetime_cdmx()")
        lines[i] = lines[i].replace("datetime.today()", "obtener_datetime_cdmx()")
    content = '\n'.join(lines)

    pattern = r"def obtener_info_tiempo_actual\(\):.*?return hora_actual, dia_actual"
    
    new_func = """def obtener_info_tiempo_actual():
    \"\"\"Obtiene la hora actual de CDMX mediante pytz y el día de la semana actual en inglés corto\"\"\"
    ahora = obtener_datetime_cdmx()
    hora_actual = ahora.time()
    mapeo_dias = {
        0: "Mon", 1: "Tue", 2: "Wed", 3: "Thu", 
        4: "Fri", 5: "Sat", 6: "Sun"
    }
    dia_actual = mapeo_dias[ahora.weekday()]
    return hora_actual, dia_actual"""
    
    content = re.sub(pattern, new_func, content, flags=re.DOTALL)

    with open('c:/wamp64/www/AXO/APIS/Asignacion/app.py', 'w', encoding='utf-8') as f:
        f.write(content)
    print("Time fix applied")

if __name__ == "__main__":
    main()
