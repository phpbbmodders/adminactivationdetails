#!/usr/bin/env python3
"""Capture disposable-board SMTP messages locally; never relay mail."""
import socketserver
import sys
import time
from pathlib import Path

if len(sys.argv) != 2 or sys.argv[1] in ('-h', '--help'):
    print('Usage: python3 smtp_capture.py OUTPUT_DIRECTORY')
    raise SystemExit(0)

folder = Path(sys.argv[1]).resolve()
folder.mkdir(parents=True, exist_ok=True)


class SMTPHandler(socketserver.StreamRequestHandler):
    """Handle the small SMTP subset used by phpBB's test messages."""
    def handle(self) -> None:
        self.connection.settimeout(20)
        self.wfile.write(b'220 localhost test capture\r\n')
        while line := self.rfile.readline(8192):
            command = line.split(b' ', 1)[0].strip().upper()
            if command in (b'EHLO', b'HELO'):
                self.wfile.write(b'250-localhost\r\n250 8BITMIME\r\n')
            elif command in (b'MAIL', b'RCPT', b'RSET', b'NOOP'):
                self.wfile.write(b'250 OK\r\n')
            elif command == b'DATA':
                self.wfile.write(b'354 End with a dot\r\n')
                chunks = []
                while data := self.rfile.readline(65536):
                    if data == b'.\r\n':
                        break
                    chunks.append(
                        data[1:] if data.startswith(b'..') else data
                    )
                target = folder / f'{time.time_ns()}.eml'
                target.write_bytes(b''.join(chunks))
                self.wfile.write(b'250 Captured\r\n')
            elif command == b'QUIT':
                self.wfile.write(b'221 Bye\r\n')
                break
            else:
                self.wfile.write(b'502 Not supported\r\n')


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


with Server(('127.0.0.1', 18250), SMTPHandler) as server:
    print('SMTP capture ready on localhost:18250', flush=True)
    server.serve_forever()
