<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista previa del prompt</title>
</head>
<body>
    <main style="max-width: 960px; margin: 2rem auto; padding: 2rem; font-family: sans-serif;">
        <h1>PREVIEW PARA DESARROLLO: prompt generado</h1>
        <p>Esta vista solo permite inspeccionar el contenido que podría enviarse a un futuro proveedor LLM. No realiza llamadas externas.</p>
        <p><strong>Marca:</strong> {{ $brand->name }}</p>
        <p><strong>Borrador:</strong> {{ $draft->title }}</p>

        <pre style="white-space: pre-wrap; background: #f8fafc; padding: 1rem; border-radius: 0.75rem;">{{ $prompt->render() }}</pre>
    </main>
</body>
</html>
