import os

files = ['dashboard.php', 'log_general.php', 'plantillas.php', 'reportes.php']

for f in files:
    try:
        with open('c:/wamp64/www/AXO/' + f, 'r', encoding='utf-8') as file:
            content = file.read()
        
        # Inyectar iconos
        content = content.replace('>Técnicos</a>', '>Técnicos <i class="bi bi-people"></i></a>')
        content = content.replace('>Plantillas</a>', '>Plantillas <i class="bi bi-file-earmark-text"></i></a>')
        content = content.replace('>Auditoría</a>', '>Auditoría <i class="bi bi-shield-check"></i></a>')
        
        # Inyectar teams menu
        rep_orig = '<li class="nav-item"><a class="nav-link" href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>'
        rep_act = '<li class="nav-item"><a class="nav-link active" href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>'
        nw = '<li class="nav-item"><a class="nav-link" href="reporte_teams.php">Bot Teams <i class="bi bi-robot"></i></a></li>'
        
        content = content.replace(rep_orig, rep_orig + '\n                    ' + nw)
        content = content.replace(rep_act, rep_act + '\n                    ' + nw)
        
        with open('c:/wamp64/www/AXO/' + f, 'w', encoding='utf-8') as file:
            file.write(content)
        print('Patched ' + f)
    except Exception as e:
        print(f"Error {f}: {e}")
