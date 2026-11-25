"""Static file server with CORS, for importing a spike bundle into a dev page."""
import http.server
import sys


class Handler(http.server.SimpleHTTPRequestHandler):
    extensions_map = {**http.server.SimpleHTTPRequestHandler.extensions_map, '.js': 'text/javascript'}

    def end_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Cache-Control', 'no-store')
        super().end_headers()


if __name__ == '__main__':
    directory, port = sys.argv[1], int(sys.argv[2])
    handler = lambda *a, **k: Handler(*a, directory=directory, **k)
    http.server.ThreadingHTTPServer(('127.0.0.1', port), handler).serve_forever()
