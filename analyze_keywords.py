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
    all_unigrams = []
    all_bigrams = []
    all_trigrams = []
    
    for asunto in group['Asunto']:
        words = clean_text(asunto)
        if not words:
            continue
            
        all_unigrams.extend(words)
        
        # Bigrams
        if len(words) >= 2:
            all_bigrams.extend([f"{w1} {w2}" for w1, w2 in zip(words[:-1], words[1:])])
            
        # Trigrams
        if len(words) >= 3:
            all_trigrams.extend([f"{w1} {w2} {w3}" for w1, w2, w3 in zip(words[:-2], words[1:-1], words[2:])])
    
    if not all_unigrams:
        continue
        
    # Count frequencies
    counter_uni = Counter(all_unigrams)
    counter_bi = Counter(all_bigrams)
    counter_tri = Counter(all_trigrams)
    
    # Get top 5 keywords
    top_words = [word for word, count in counter_uni.most_common(5) if count > 1]
    
    # Get top 5 combinations (bigrams + trigrams)
    top_combinations = []
    for phrase, count in counter_bi.most_common(5):
        if count > 1:
            top_combinations.append(phrase)
            
    for phrase, count in counter_tri.most_common(3):
        if count > 1:
            top_combinations.append(phrase)
    
    if top_words or top_combinations:
        plantilla_name = f"{name[0]} > {name[1]} > {name[2]}"
        results[plantilla_name] = {
            'palabras_clave': top_words,
            'combinaciones_clave': top_combinations,
            'volumen_tickets': len(group)
        }

# Sort by volume to show the most important ones first
sorted_results = dict(sorted(results.items(), key=lambda x: x[1]['volumen_tickets'], reverse=True))

# Take top 20 for brevity
top_20 = {k: sorted_results[k] for k in list(sorted_results.keys())[:20]}

with open('c:/wamp64/www/AXO/keywords_output.json', 'w', encoding='utf-8') as f:
    json.dump(top_20, f, ensure_ascii=False, indent=2)

print("Analysis complete. Found", len(sorted_results), "templates with keywords. Top 20 saved.")
