# Sistema web para la planificación y gestión de rutas, vehículos y reportes en la Unidad de Logística de la Universidad de Aysén.

# 📦 Gestión y Organización para la Unidad de Logística y Servicios Generales

Sistema web desarrollado para apoyar la planificación, gestión y control de recursos de transporte de la Unidad de Logística y Servicios Generales de la Universidad de Aysén. Este sistema está diseñado para facilitar el registro de rutas, la asignación de vehículos, la consulta de disponibilidad y la generación de reportes.

> 🚧 Proyecto en desarrollo — versión de avance bajo metodología Scrum.

---

## 📌 Índice

- [🎯 Objetivo](#-objetivo)
- [🛠️ Tecnologías](#️-tecnologías)
- [📐 Arquitectura](#-arquitectura)
- [📁 Estructura del Proyecto](#-estructura-del-proyecto)
- [🧪 Instalación Local](#-instalación-local)
- [📋 Funcionalidades](#-funcionalidades)
- [🔒 Consideraciones de Seguridad](#-consideraciones-de-seguridad)
- [🤝 Colaboradores](#-colaboradores)
- [📄 Licencia](#-licencia)

---

## 🎯 Objetivo

Desarrollar una plataforma web privada que permita mejorar la gestión del transporte institucional mediante:
- Planificación de rutas.
- Asignación dinámica de vehículos.
- Consulta en tiempo real del estado de unidades.
- Generación de reportes operativos mensuales.
- Control y trazabilidad de solicitudes.

---

## 🛠️ Tecnologías

- **Lenguaje Backend:** PHP
- **Base de Datos:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Servidor Local:** XAMPP
- **Control de versiones:** Git + GitHub
- **Metodología:** Scrum

---

## 📐 Arquitectura

Arquitectura basada en capas:

- **Capa de Presentación:** Interfaces para login, panel principal, planificador de rutas y reportes.
- **Capa de Dominio:** Lógica del negocio (autenticación, planificación, gestión de vehículos y reportes).
- **Capa de Datos:** Estructura relacional con tablas como `usuarios`, `vehiculos`, `rutas`, `asignaciones`, entre otras.

---



## 📁 Estructura del Proyecto

```bash
miniature-umbrella/
├── php/
│   ├── admin/
├── assets/             # Recursos como estilos CSS, JS y multimedia
│   ├── css/
│   └── js/
├── db/                 # Scripts SQL, respaldos y documentación de la base de datos
├── README.md
├── .gitignore
```
## 🧪 Instalación Local

1. Clona este repositorio:  
   `git clone https://github.com/FMICInf/miniature-umbrella.git`

2. Copia el proyecto dentro de la carpeta `htdocs` de XAMPP.

3. Importa la base de datos:
   - Abre **phpMyAdmin**.
   - Crea una base de datos llamada **logistica**.
   - Importa el archivo `.sql` ubicado en la carpeta `/db`.

4. Abre tu navegador y accede a:  
   `http://localhost/miniature-umbrella`

---
## 📋 Funcionalidades

### Funcionalidades principales implementadas

- ✅ Autenticación y control de acceso  
  Login seguro por roles (administrador, conductor, usuario).  
  Control de sesión y redirección según permisos.

- ✅ Gestión de rutas  
  Registro y edición de rutas con origen, destino y horarios.  
  Visualización de rutas disponibles para planificación.

- ✅ Asignación de vehículos  
  Asignación de vehículos a rutas con fechas específicas.  
  Visualización del estado de cada unidad (activo, reservado, disponible, inactivo).

- ✅ Generación de reportes  
  Reportes mensuales de uso de transporte.  
  Posibilidad de exportar o imprimir.

- ✅ Gestión de solicitudes  
  Creación, edición o cancelación de solicitudes por parte de los usuarios.  
  Aprobación y asignación por parte del administrador.

### Funcionalidades en desarrollo o futuras

- ⏳ Validación automática de disponibilidad en tiempo real.  
- ⏳ Módulo de notificaciones internas al asignar rutas.  
- ⏳ Backup automático cada 24 horas.  
- ⏳ Panel de métricas con visualizaciones gráficas.

---
## 🔒 Consideraciones de Seguridad

- Validación de credenciales y sesiones activas.  
- Restricción de acceso según roles definidos.  
- Respaldo y trazabilidad del sistema mediante control de versiones (Git).  
- Cumplimiento de políticas internas de seguridad institucional (Universidad de Aysén, Resolución N.º 368).  

---

## 🤝 Colaboradores

- 👨‍💻 **Francisco Marió Chiguay**  
  *Desarrollador - Estudiante Ingeniería Civil Informática*

- 🧑‍💼 **René Peña Ulloa**  
  *Socio Comunitario - Unidad de Logística y Servicios Generales*

---

## 📄 Licencia

Este proyecto es de uso **privado** y está destinado exclusivamente a la Unidad de Logística y Servicios Generales de la Universidad de Aysén.  
**Prohibida su distribución sin autorización expresa del socio comunitario.**


