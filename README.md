# 🎲 La Taberna de las Gatitas Jugonas (`BoardGameManager`)

Aplicación web de gestión comunitaria para grupos de juegos de mesa, diseñada bajo una dirección artística de **"Taberna Clásica"** (texturas de pergamino, madera oscura, hierro forjado y lacre, libre de frameworks pesados). Permite gestionar el catálogo de juegos del grupo, coordinar compras o deseos, organizar votaciones para las quedadas con avisos por correo electrónico y llevar el registro histórico de campeones.

---

## ✨ Funcionalidades Principales

### 🍺 1. Salón Principal (`index.php`)
* **Panel de estadísticas en tiempo real:** Muestra el total de juegos disponibles en la taberna, juegos en la lista de deseados y votaciones activas en curso.
* **Última Partida Ganada:** Destaca al ganador más reciente junto con el juego disputado y la fecha del encuentro.
* **Accesos rápidos:** Navegación directa hacia todas las estancias de la taberna según el rol del usuario.

### 📚 2. Ludoteca y Sistema de Reseñas (`ludoteca.php`)
* **Catálogo visual interactivo:** Cuadrícula con todos los juegos que el grupo ya posee (`lo_tenemos = 1`).
* **Cálculo automático de nota media:** Cada tarjeta muestra una insignia cromática con la puntuación media de la taberna (escala de **1 a 5**: *1 - No me gustó*, *2 - Meh*, *3 - Está Bien*, *4 - Me gustó bastante*, *5 - ¿Pa cuando otra?*).
* **Filtros y ordenación en vivo (sin recargar la página):**
  * Búsqueda instantánea por nombre.
  * Filtro por estado personal (*Todos*, *Jugados por mí*, *No jugados por mí*, *No reseñados por mí*).
  * Ordenación alfabética (`A - Z`) o por preferencias de la comunidad (mayor nota media).
* **Ficha detallada del juego:** Permite registrar si has jugado al título, asignarle tu nota y dejar una reseña escrita visible para el resto de miembros.

### 🎯 3. Juegos Deseados e Interesados (`juegos.php` y `alta_juego.php`)
* **Alta de juegos con subida de portadas (`alta_juego.php`):** Validación en cliente y servidor para imágenes (`JPG`, `PNG`, `GIF`, `WEBP` de hasta **5 MB**) con previsualización instantánea antes de subir.
* **Lista de interés (`juegos.php`):** Muestra los juegos que el grupo aún no tiene (`lo_tenemos = 0`). Cualquier parroquiano puede apuntarse o desapuntarse con un clic para mostrar su interés en adquirirlo o probarlo.
* **Cambio de disponibilidad:** Cuando el grupo consigue un juego, cualquier miembro puede marcarlo como *«Ya lo tenemos»*, transfiriéndolo automáticamente a la Ludoteca, las Votaciones y el Leaderboard, y limpiando la lista de espera previa.

### 🏆 4. Tablón de Campeones — Leaderboard (`leaderboard.php`)
* **Registro de victorias:** Permite anotar al ganador de cada partida disputada entre los juegos disponibles en la taberna.
* **Clasificación General y por Juego:** Ranking automático de todos los miembros ordenado por victorias acumuladas, con posibilidad de filtrar la tabla para ver quién domina un juego de mesa concreto.
* **Historial reciente:** Registro cronológico de las últimas 25 partidas ganadas.

### 🗳️ 5. Consejo de Votaciones y Notificaciones por Correo (`votaciones.php`)
* **Convocatoria de votaciones:** Cualquier miembro puede abrir una votación definiendo el motivo/título, la fecha límite y la modalidad (**Voto Único** o **Voto Múltiple**).
* **Aviso automático por Email (SMTP):** Al convocar una nueva votación, el sistema envía una notificación con diseño de pergamino vía SMTP + STARTTLS a todos los miembros con correo electrónico registrado.
* **Sistema de resaltado inteligente:** En las votaciones activas, se destacan automáticamente los juegos que lideran la preferencia por encima del mínimo de votos, marcando con la insignia *«Más votado»* a los que ostentan el primer puesto.
* **Gestión por el creador y archivo automático:** El autor puede editar el título, ampliar la fecha límite o cerrar la votación anticipadamente. Una vez vencido el plazo, los resultados permanecen visibles durante 1 día antes de archivarse.

### 🔑 6. Autenticación, Perfil y Despacho del Tabernero (`login.php`, `perfil.php`, `admin.php`)
* **Acceso y alta rápida (`login.php`):** Inicio de sesión y registro de nuevos parroquianos en pestañas dinámicas.
* **Despacho del Tabernero (`admin.php`):** Panel de administración exclusivo para usuarios con rol `admin` que permite inspeccionar, crear, editar en línea o eliminar registros de las **7 tablas del sistema** (`Persona`, `Juego`, `Persona_Juego`, `Victoria`, `Votacion`, `Voto` y `Resenya`), con actualización y borrado en cascada (`ON UPDATE CASCADE ON DELETE CASCADE`).

---

## 🛠️ Arquitectura y Stack Tecnológico

* **Backend:** PHP 8+ estructurado sin dependencias pesadas, utilizando **PDO** con sentencias preparadas (`ATTR_EMULATE_PREPARES => false`) y escapado estricto anti-XSS (`h()`).
* **Base de Datos:** MySQL / MariaDB con motor `InnoDB` y codificación `utf8mb4_unicode_ci`, sincronizada dinámicamente con el huso horario `Europe/Madrid`.
* **Frontend:** HTML5 semántico, CSS3 a medida (`styles.css`) con diseño *Responsive* (adaptado a móvil y escritorio, tablas con *sticky header* y scroll interno) y **JavaScript Vanilla** (`app.js`) desacoplado mediante atributos `data-*`.
* **CI/CD y Hosting:** Despliegue continuo mediante **GitHub Actions** (`.github/workflows/deploy.yml`) hacia **InfinityFree** vía FTP, inyectando las credenciales de base de datos y SMTP de forma segura desde *GitHub Secrets*.

---

## 📂 Estructura del Repositorio

```text
BoardGameManager/
├── .github/workflows/
│   └── deploy.yml          # Automatización de despliegue FTP e inyección de secretos
├── sql/
│   └── database.sql        # Esquema completo de tablas y relaciones en cascada
├── img/
│   └── logo.png            # Emblema y favicon de la taberna
├── uploads/                # Almacenamiento de portadas subidas por los usuarios
├── conexion.php            # Conexión PDO, control de sesiones, subida de imágenes y cliente SMTP
├── index.php               # Salón Principal (Dashboard)
├── login.php               # Autenticación y registro de usuarios
├── logout.php              # Cierre de sesión seguro
├── perfil.php              # Gestión del perfil del usuario
├── ludoteca.php            # Catálogo de juegos disponibles y reseñas (1-5)
├── juegos.php              # Lista de juegos deseados e interesados
├── alta_juego.php          # Formulario de registro de nuevos juegos
├── leaderboard.php         # Clasificación de victorias e historial de partidas
├── votaciones.php          # Creación y participación en votaciones de juegos
├── admin.php               # Panel CRUD de administración integral
├── styles.css              # Estilos visuales "Taberna Clásica"
└── app.js                  # Interacciones en cliente (filtros, ordenación, previsualización)