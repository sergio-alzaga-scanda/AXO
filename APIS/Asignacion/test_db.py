from db import get_connection

try:
    connection = get_connection()
    with connection.cursor() as cursor:
        # Ejecutamos una consulta simple que no dependa de tus tablas
        cursor.execute("SELECT VERSION();")
        version = cursor.fetchone()
        print(f"✅ Conexión exitosa. Versión de MySQL: {version['VERSION()']}")
    connection.close()
except Exception as e:
    print(f"❌ Error al conectar a la base de datos: {e}")