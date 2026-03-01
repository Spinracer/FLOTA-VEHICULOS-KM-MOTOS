<?php
/**
 * Swagger UI — Documentación interactiva de FlotaControl API v1
 * Usa Swagger UI desde CDN para renderizar la spec OpenAPI.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlotaControl API v1 — Documentación</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d1117; }
        .topbar-wrapper img { content: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>'); }
        .swagger-ui .topbar { background: #1a1f2e; padding: 10px 20px; }
        .swagger-ui .topbar .download-url-wrapper { display: flex; align-items: center; }
        .fc-header { background: #1a1f2e; padding: 16px 24px; display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #47ffe8; }
        .fc-header .logo { color: #47ffe8; font-size: 20px; font-weight: 700; font-family: 'Segoe UI', sans-serif; }
        .fc-header .version { background: #47ffe8; color: #1a1f2e; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .fc-header .links { margin-left: auto; display: flex; gap: 12px; }
        .fc-header .links a { color: #8892a4; text-decoration: none; font-size: 13px; transition: color .2s; }
        .fc-header .links a:hover { color: #47ffe8; }
        .swagger-ui { background: #0d1117; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .scheme-container { background: #161b22; border: 1px solid #30363d; }
        /* Dark theme overrides */
        .swagger-ui .opblock-tag { color: #c9d1d9; border-bottom-color: #30363d; }
        .swagger-ui .opblock { border-color: #30363d; background: #161b22; }
        .swagger-ui .opblock .opblock-summary { border-color: #30363d; }
        .swagger-ui .opblock-description-wrapper, .swagger-ui .opblock-body { background: #0d1117; }
        .swagger-ui .btn { border-radius: 4px; }
        .swagger-ui .model-box { background: #161b22; }
        .swagger-ui section.models { border-color: #30363d; }
        .swagger-ui .model-title { color: #c9d1d9; }
        .swagger-ui table thead tr th, .swagger-ui table thead tr td { color: #c9d1d9; border-bottom-color: #30363d; }
        .swagger-ui .response-col_status { color: #c9d1d9; }
        .swagger-ui .parameter__name { color: #c9d1d9; }
        .swagger-ui .parameter__type { color: #8b949e; }
        .swagger-ui p, .swagger-ui .markdown p { color: #8b949e; }
        .swagger-ui h3, .swagger-ui h4, .swagger-ui h5 { color: #c9d1d9; }
    </style>
</head>
<body>
    <div class="fc-header">
        <span class="logo">🚗 FlotaControl API</span>
        <span class="version">v1.0</span>
        <div class="links">
            <a href="/api/v1/openapi.json" target="_blank">📄 OpenAPI JSON</a>
            <a href="/api/v1/health">❤️ Health</a>
            <a href="/">🏠 App</a>
        </div>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: '/api/v1/openapi.json',
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: 'BaseLayout',
            defaultModelsExpandDepth: 1,
            docExpansion: 'list',
            filter: true,
            syntaxHighlight: { theme: 'monokai' },
        });
    </script>
</body>
</html>
