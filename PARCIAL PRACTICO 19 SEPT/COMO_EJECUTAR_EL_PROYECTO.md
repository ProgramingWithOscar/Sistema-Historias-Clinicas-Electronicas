# Cómo ejecutar el proyecto

Sistema de Historias Clínicas Electrónicas — guía de puesta en marcha.

Todo el sistema corre en Docker: **no hace falta instalar PHP, Composer, Node ni
MySQL** en la máquina, ni configurar nada. El archivo de configuración
(`.env`) ya viene incluido en el `.zip` con las credenciales listas.


---

## 1. Requisitos

| Requisito | Comprobar con | Versión mínima |
|---|---|---|
| Docker | `docker --version` | 24 |
| Docker Compose | `docker compose version` | v2 |

Los puertos **8000** y **5173** deben estar libres. Si alguno está ocupado, ver
el último apartado de esta guía.

---

## 2. Descomprimir y entrar a la carpeta


Debe existir un archivo `.env` en esa carpeta (es un archivo oculto; se ve con
`ls -a`). Ya trae la llave de la aplicación y las contraseñas de la base de
datos, así que **no hay que tocarlo**.

---

## 3. Levantar el sistema

```bash
docker compose up -d --build
```

La primera vez tarda varios minutos: descarga las imágenes de PHP, MySQL, Redis
y Node, instala dependencias y compila el frontend. Las migraciones de la base
de datos se ejecutan solas al arrancar.

Para comprobar que los seis servicios quedaron arriba:

```bash
docker compose ps
```

Deben aparecer `backend`, `db`, `frontend`, `nginx`, `queue` y `redis` en estado
`Up`, con `db` marcado como `healthy`.

---

## 4. Cargar los datos de demostración

**Este paso es obligatorio**: sin él la base queda vacía y no existe ningún
usuario con el que iniciar sesión.

```bash
docker compose exec backend php artisan db:seed --force
```

Crea dos usuarios, cuatro lecturas de dispositivos IoT y una nota de atención ya
registrada. Puede ejecutarse varias veces sin duplicar nada.

---

## 5. Abrir la aplicación

| | Dirección |
|---|---|
| **Aplicación web** | **http://localhost:5173** |
| API (sólo si se quiere inspeccionar) | http://localhost:8000/api |

### Credenciales de acceso

| Correo | Contraseña |
|---|---|
| `medico@hce.test` | `password` |

Se entra como **Dra. Helena Ruiz**, profesional de salud. Existe un segundo
usuario, `paciente@hce.test` (misma contraseña), que es la paciente sobre la que
están cargados los datos de muestra.

---