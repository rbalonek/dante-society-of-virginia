const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 3000;
const MIME_TYPES = {
    '.html': 'text/html',
    '.css': 'text/css',
    '.js': 'application/javascript',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.pdf': 'application/pdf',
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    '.txt': 'text/plain',
    '.md': 'text/markdown',
    '.xml': 'application/xml',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
};

const PUBLIC_DIR = __dirname;

const server = http.createServer((req, res) => {
    // Default to index.html for root
    let filePath = req.url === '/' ? '/index.html' : req.url;

    // Decode URL
    filePath = decodeURIComponent(filePath);

    // Build absolute path
    let absolutePath = path.join(PUBLIC_DIR, filePath);

    // Security: prevent directory traversal
    if (!absolutePath.startsWith(PUBLIC_DIR)) {
        res.writeHead(403);
        res.end('403 Forbidden');
        return;
    }

    // Check if path exists
    fs.stat(absolutePath, (err, stats) => {
        if (err) {
            // Try adding .html for nice URLs
            if (!filePath.endsWith('.html') && !path.extname(filePath)) {
                const htmlPath = absolutePath + '.html';
                return fs.stat(htmlPath, (err2, stats2) => {
                    if (!err2 && stats2.isFile()) {
                        serveFile(htmlPath, res);
                    } else {
                        serve404(res);
                    }
                });
            }
            serve404(res);
            return;
        }

        if (stats.isDirectory()) {
            // Try index.html in directory
            const indexPath = path.join(absolutePath, 'index.html');
            fs.stat(indexPath, (err2, stats2) => {
                if (!err2 && stats2.isFile()) {
                    serveFile(indexPath, res);
                } else {
                    serve404(res);
                }
            });
        } else {
            serveFile(absolutePath, res);
        }
    });
});

function serveFile(filePath, res) {
    const ext = path.extname(filePath).toLowerCase();
    const contentType = MIME_TYPES[ext] || 'application/octet-stream';

    fs.readFile(filePath, (err, data) => {
        if (err) {
            res.writeHead(500);
            res.end('500 Internal Server Error');
            return;
        }
        res.writeHead(200, { 'Content-Type': contentType });
        res.end(data);
    });
}

function serve404(res) {
    const notFoundPath = path.join(PUBLIC_DIR, '404.html');
    fs.readFile(notFoundPath, (err, data) => {
        res.writeHead(404, { 'Content-Type': 'text/html' });
        if (!err) {
            res.end(data);
        } else {
            res.end('<h1>404 - Page Not Found</h1><p><a href="/">Return Home</a></p>');
        }
    });
}

server.listen(PORT, () => {
    console.log(`\n  🎭 Dante Society of Virginia`);
    console.log(`  ─────────────────────────────`);
    console.log(`  Server running at:`);
    console.log(`  → http://localhost:${PORT}/\n`);
});
