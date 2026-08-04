

# FVN.li - Análisis y Seguimiento de Novelas Visuales

[![Desplegar documentación de Writerside](https://github.com/AkibaAT/fvn.li/actions/workflows/deploy-docs.yml/badge.svg)](https://github.com/AkibaAT/fvn.li/actions/workflows/deploy-docs.yml)

FVN.li es una aplicación web que realiza el seguimiento, analiza y proporciona información sobre los juegos publicados en itch.io. Recopila datos sobre los juegos, sus versiones, calificaciones y contenido de diálogos, facilitando a los usuarios descubrir y evaluar juegos en la plataforma. El proyecto está desplegado y es accesible en [FVN.li](https://fvn.li).

## Documentación

**[Ver Documentación](https://akibaat.github.io/fvn.li/)** - Documentación completa desarrollada con Writerside y desplegada en GitHub Pages.

## Características

- **Seguimiento de Juegos**: Monitorear juegos publicados en itch.io, incluyendo metadatos, versiones y calificaciones
- **Explorador de Diálogos**: Explorar el contenido de diálogos del juego a través de diferentes versiones e idiomas
- **Sistema de Calificaciones**: Ver y analizar las calificaciones de los juegos por parte de la comunidad
- **Soporte de Idiomas**: Rastrear los idiomas compatibles con los juegos y analizar la cobertura de traducción
- **Estadísticas de Personajes**: Ver estadísticas de personajes y distribución de diálogos
- **Integración con Bot de Discord**: Recibir notificaciones sobre actualizaciones de juegos a través de Discord

## Tecnologías

- **Backend**: Laravel 13.7 con PHP 8.5
- **Frontend**: Svelte 5.55 con TypeScript, Inertia.js 2.3, Tailwind CSS 4.2
- **Herramienta de Construcción**: Vite 8 con soporte para SSR
- **Base de Datos**: PostgreSQL 17
- **Búsqueda**: Meilisearch para búsqueda de texto completo
- **Caché**: Redis
- **Desarrollo**: DDEV para entorno de desarrollo local
- **Visualización**: Chart.js con componentes de gráficos Svelte para visualización de datos
- **Pruebas**: PHPUnit/Pest, Vitest para unidades Svelte, y Playwright para pruebas E2E/de accesibilidad a través del sidecar Playwright de DDEV
- **Despliegue**: Docker para despliegue en contenedores
- **API**: Endpoints de API RESTful para integración con bot de Discord

Para información detallada sobre la arquitectura, consulta la documentación de [Arquitectura del Frontend](https://akibaat.github.io/fvn.li/frontend-architecture.html).

## Primeros Pasos

### Requisitos Previos

- [Docker](https://www.docker.com/get-started)
- [DDEV](https://ddev.readthedocs.io/en/stable/)
- [Composer](https://getcomposer.org/)
- [Bun](https://bun.sh/)

### Configuración para Desarrollo Local

1. Clona el repositorio:
   ```bash
   git clone https://github.com/AkibaAT/fvn.li.git
   cd fvn.li
   ```

2. DDEV descarga la CLI oficial de itch.io `butler` en la imagen web y ejecuta la imagen `ghcr.io/akibaat/denkit-stash:development` para el servidor local DenKit Stash.

3. Inicia el entorno DDEV:
   ```bash
   ddev start
   ```

4. Instala las dependencias de PHP:
   ```bash
   ddev composer install
   ```

5. Instala las dependencias de JavaScript:
   ```bash
   ddev bun install
   ```

6. Copia el archivo de entorno y genera una clave de aplicación:
   ```bash
   cp .env.example .env
   ddev artisan key:generate
   ```

7. Ejecuta las migraciones de la base de datos:
   ```bash
   ddev artisan migrate
   ```

8. Inicia el servidor de desarrollo de Vite:
   ```bash
   ddev bun dev
   ```

9. Accede a la aplicación en [https://fvn-li.ddev.site](https://fvn-li.ddev.site)

Para instrucciones de desarrollo más detalladas, consulta la [Guía de Desarrollo](https://akibaat.github.io/fvn.li/development-guide.html).

## Estructura de la Base de Datos

La aplicación utiliza varios modelos clave:

- **Game**: Información principal del juego desde itch.io
- **GameVersion**: Rastrea diferentes versiones de los juegos
- **Rater**: Usuarios que califican juegos
- **Rating**: Calificaciones individuales para juegos
- **DialogueLine**: Contenido de diálogos del juego
- **Character**: Personajes en los juegos
- **Language**: Idiomas compatibles para los juegos
- **DiscordUser**: Usuarios de Discord suscritos a actualizaciones de juegos

## Despliegue con Docker

La aplicación puede desplegarse usando Docker en entornos de producción:

1. Configura las variables de entorno en `.env`
2. Usa el `docker-compose.yml` proporcionado para iniciar la aplicación:
   ```bash
   docker compose up -d
   ```

Esto iniciará los siguientes contenedores:

- Aplicación web (Laravel)
- Base de datos PostgreSQL
- Redis para caché

## Integración con Bot de Discord

El bot de Discord se autentica con un token bearer de Laravel Sanctum. Emite uno para un usuario local existente:

```bash
ddev artisan discord:issue-api-token bot@example.com
```

Usa `--replace` al rotar un token con el mismo nombre. Almacena el valor impreso en el entorno del bot como su token de API de fvn.li y envíalo con cada solicitud:

```http
Authorization: Bearer <token>
Accept: application/json
```

El token emitido tiene dos capacidades verificadas de forma estricta:

- `discord-bot`: búsqueda/búsqueda de juegos y ciclo de vida del servidor `/api/bot/servers/*`, sincronización de canal/miembro y endpoints de entrega en cola.
- `discord-notifications`: endpoints de sondeo de notificaciones `/api/discord-notifications/*` y estado de entrega.

Las rutas `/browser-api/discord/*` dirigidas al navegador usan la sesión web iniciada y no son APIs para bots.

## Contribuciones

¡Las contribuciones son bienvenidas! No dudes en enviar un Pull Request.

1. Haz un fork del repositorio
2. Crea tu rama de funcionalidad (`git checkout -b feature/amazing-feature`)
3. Confirma tus cambios (`git commit -m 'Agrega una increíble funcionalidad'`)
4. Envía a la rama (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

## Pruebas

### Pruebas de Backend (PHPUnit)

Ejecuta el conjunto de pruebas con el entorno de pruebas (servido en https://fvn-li-testing.ddev.site):

```bash
ddev artisan test --env=testing
# or, via Composer script (also uses --env=testing)
ddev composer test
```

Para la cobertura, usa:

```bash
ddev composer test:coverage:clover
ddev composer test:coverage:audit
```

Restablece la base de datos de pruebas cuando sea necesario:

```bash
ddev composer migrate:test
```

### Pruebas de Frontend

Ejecuta las pruebas unitarias de Svelte con Vitest:

```bash
ddev bun test:js
```

### Pruebas E2E de Frontend (Playwright)

Playwright se ejecuta contra el servicio sidecar oficial de Playwright configurado en DDEV. Las dependencias del navegador no están instaladas en el contenedor web.

```bash
# Ejecutar todas las pruebas E2E
ddev playwright test

# Ejecutar en modo UI (interactivo)
ddev playwright test --ui

# Ejecutar pruebas de accesibilidad
ddev playwright test tests/e2e/specs/accessibility.spec.ts --grep @accessibility

# Ver informe de pruebas
ddev bun test:a11y:report
```

### Calidad del Código

```bash
# Verificación de tipos de TypeScript
ddev bun types

# ESLint
ddev bun lint

# Formateo de Prettier
ddev bun format
ddev bun format:check
```

## Convenciones de DDEV

- Ejecuta Composer y bun dentro de DDEV: `ddev composer <cmd>`, `ddev bun <cmd>`.
- Linting de PHP: `ddev composer lint` (PHP/Duster), `ddev composer lint:fix`.
- Linting de Frontend: `ddev bun lint`, `ddev bun format`.
- Los hooks de Git ejecutan linters y pruebas a través de DDEV. Habilítalos con `composer hooks:install` y asegúrate de que sean ejecutables.
- URL de Pruebas: `fvn-li-testing.ddev.site` está configurada a través de `additional_fqdns` de DDEV. Ejecuta `ddev restart` después de aplicar cambios de configuración.
- URL de Desarrollo: `fvn-li.ddev.site` (URL predeterminada del proyecto DDEV).

## Despliegue

La aplicación está desplegada en [FVN.li](https://fvn.li). El despliegue se maneja a través de GitHub Actions, que construye y publica imágenes de Docker en el Registro de Contenedores de GitHub.

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - consulta el archivo LICENSE para más detalles.

## Agradecimientos

- [Laravel](https://laravel.com) - Framework web en PHP
- [Svelte](https://svelte.dev) - Framework de UI
- [Inertia.js](https://inertiajs.com) - Framework monolítico moderno
- [Vite](https://vitejs.dev) - Herramienta de construcción para frontend
- [TypeScript](https://www.typescriptlang.org) - JavaScript con tipos
- [Tailwind CSS](https://tailwindcss.com) - Framework CSS
- [Chart.js](https://www.chartjs.org) - Biblioteca de gráficos
- [Playwright](https://playwright.dev) - Framework de pruebas E2E
- [itch.io](https://itch.io) - Plataforma de distribución de juegos
- [Discord](https://discord.com) - Plataforma de integración de bots
- [DDEV](https://ddev.com) - Entorno de desarrollo local
