import pandas as pd
import re
from collections import Counter
import json

stop_words = set(['de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no', 'una', 'su', 'al', 'lo', 'como', 'más', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'sí', 'porque', 'esta', 'entre', 'cuando', 'muy', 'sin', 'sobre', 'también', 'me', 'hasta', 'hay', 'donde', 'quien', 'desde', 'todo', 'nos', 'durante', 'todos', 'uno', 'les', 'ni', 'contra', 'otros', 'ese', 'eso', 'ante', 'ellos', 'e', 'esto', 'mí', 'antes', 'algunos', 'qué', 'unos', 'yo', 'otro', 'otras', 'otra', 'él', 'tanto', 'esa', 'estos', 'mucho', 'quienes', 'nada', 'muchos', 'cual', 'poco', 'ella', 'estar', 'estas', 'algunas', 'algo', 'nosotros', 'mi', 'mis', 'tú', 'te', 'ti', 'tu', 'tus', 'ellas', 'nosotras', 'vosotros', 'vosotras', 'os', 'mío', 'mía', 'míos', 'mías', 'tuyo', 'tuya', 'tuyos', 'tuyas', 'suyo', 'suya', 'suyos', 'suyas', 'nuestro', 'nuestra', 'nuestros', 'nuestras', 'vuestro', 'vuestra', 'vuestros', 'vuestras', 'esos', 'esas', 'estoy', 'estás', 'está', 'estamos', 'estáis', 'están', 'esté', 'estés', 'estemos', 'estéis', 'estén', 'estaré', 'estarás', 'estará', 'estaremos', 'estaréis', 'estarán', 'estaría', 'estarías', 'estaríamos', 'estaríais', 'estarían', 'estaba', 'estabas', 'estábamos', 'estabais', 'estaban', 'estuve', 'estuviste', 'estuvo', 'estuvimos', 'estuvisteis', 'estuvieron', 'estuviera', 'estuvieras', 'estuviéramos', 'estuvierais', 'estuvieran', 'estuviese', 'estuvieses', 'estuviésemos', 'estuvieseis', 'estuviesen', 'estando', 'estado', 'estada', 'estados', 'estadas', 'estad', 'he', 'has', 'ha', 'hemos', 'habéis', 'han', 'haya', 'hayas', 'hayamos', 'hayáis', 'hayan', 'habré', 'habrás', 'habrá', 'habremos', 'habréis', 'habrán', 'habría', 'habrías', 'habríamos', 'habríais', 'habrían', 'había', 'habías', 'habíamos', 'habíais', 'habían', 'hube', 'hubiste', 'hubo', 'hubimos', 'hubisteis', 'hubieron', 'hubiera', 'hubieras', 'hubiéramos', 'hubierais', 'hubieran', 'hubiese', 'hubieses', 'hubiésemos', 'hubieseis', 'hubiesen', 'habiendo', 'habido', 'habida', 'habidos', 'habidas', 'soy', 'eres', 'es', 'somos', 'sois', 'son', 'sea', 'seas', 'seamos', 'seáis', 'sean', 'seré', 'serás', 'será', 'seremos', 'seréis', 'serán', 'sería', 'serías', 'seríamos', 'seríais', 'serían', 'era', 'eras', 'éramos', 'erais', 'eran', 'fui', 'fuiste', 'fue', 'fuimos', 'fuisteis', 'fueron', 'fuera', 'fueras', 'fuéramos', 'fuerais', 'fueran', 'fuese', 'fueses', 'fuésemos', 'fueseis', 'fuesen', 'siendo', 'sido', 'tengo', 'tienes', 'tiene', 'tenemos', 'tenéis', 'tienen', 'tenga', 'tengas', 'tengamos', 'tengáis', 'tengan', 'tendré', 'tendrás', 'tendrá', 'tendremos', 'tendréis', 'tendrán', 'tendría', 'tendrías', 'tendríamos', 'tendríais', 'tendrían', 'tenía', 'tenías', 'teníamos', 'teníais', 'tenían', 'tuve', 'tuviste', 'tuvo', 'tuvimos', 'tuvisteis', 'tuvieron', 'tuviera', 'tuvieras', 'tuviéramos', 'tuvierais', 'tuvieran', 'tuviese', 'tuvieses', 'tuviésemos', 'tuvieseis', 'tuviesen', 'teniendo', 'tenido', 'tenida', 'tenidos', 'tenidas', 'tened', 'falla', 'error', 'no', 'problema', 'ticket', 'reporte', 'solicitud', 'apoyo', 'ayuda', 'favor', 'requiere'])

# Extra stop words specific to IT helpdesks
stop_words.update(['falla', 'error', 'problema', 'ticket', 'reporte', 'solicitud', 'apoyo', 'ayuda', 'favor', 'requiere', 'req', 'inc', 'incidente', 'buenas', 'tardes', 'dias', 'noches', 'hola', 'urgente', 'urgencia'])

df = pd.read_excel('c:/wamp64/www/AXO/Base Enero _ Marzo 2026 AXO.xlsx')

def clean_text(text):
    if not isinstance(text, str):
        return []
    # Remove non-alphanumeric, convert to lower
    words = re.findall(r'\b[a-záéíóúñ]{4,}\b', text.lower())
    # Remove stopwords
    return [w for w in words if w not in stop_words]

# Drop rows with NaN in important columns
df = df.dropna(subset=['Asunto', 'Categoría', 'Subcategoría'])

# Group by the combination of category/subcategory/article
results = {}
groups = df.groupby(['Categoría', 'Subcategoría', 'Artículo'])

for name, group in groups:
    # Get all words in Asunto for this group
    all_words = []
    for asunto in group['Asunto']:
        all_words.extend(clean_text(asunto))
    
    if not all_words:
        continue
        
    # Count frequencies
    counter = Counter(all_words)
    # Get top 5 keywords
    top_words = [word for word, count in counter.most_common(5) if count > 1]
    
    if top_words:
        plantilla_name = f"{name[0]} > {name[1]} > {name[2]}"
        results[plantilla_name] = {
            'palabras_clave': top_words,
            'volumen_tickets': len(group)
        }

# Sort by volume to show the most important ones first
sorted_results = dict(sorted(results.items(), key=lambda x: x[1]['volumen_tickets'], reverse=True))

# Take top 20 for brevity
top_20 = {k: sorted_results[k] for k in list(sorted_results.keys())[:20]}

with open('c:/wamp64/www/AXO/keywords_output.json', 'w', encoding='utf-8') as f:
    json.dump(top_20, f, ensure_ascii=False, indent=2)

print("Analysis complete. Found", len(sorted_results), "templates with keywords. Top 20 saved.")
