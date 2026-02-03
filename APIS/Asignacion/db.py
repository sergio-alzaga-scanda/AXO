import pymysql
from config import *

def get_connection():
    return pymysql.connect(
        host=MYSQL_HOST,
        port=int(MYSQL_PORT), # Forzamos que sea un número entero
        user=MYSQL_USER,
        password=MYSQL_PASSWORD,
        db=MYSQL_DB,
        cursorclass=pymysql.cursors.DictCursor
    )