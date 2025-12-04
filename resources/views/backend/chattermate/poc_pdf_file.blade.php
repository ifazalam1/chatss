<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDF Chat PoC</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        #response { white-space: pre-wrap; border: 1px solid #ccc; padding: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Ask a Question About a PDF</h1>
    <form id="chatForm" enctype="multipart/form-data">
        <input type="text" name="message" placeholder="Your question" required>
        <input type="file" name="pdf" accept=".pdf" required>
        <button type="submit">Ask</button>
    </form>

    <div id="response"></div>

    <script>
        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('response');
            responseDiv.innerHTML = '';

            const response = await fetch('/pdf-chat', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: formData,
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let result = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                const chunk = decoder.decode(value);
                const lines = chunk.split('\n\n').filter(Boolean);
                lines.forEach(line => {
                    if (line.startsWith('data:')) {
                        const data = JSON.parse(line.replace('data: ', ''));
                        result += data.content;
                        responseDiv.innerText = result;
                    }
                });
            }
        });
    </script>
</body>
</html>
