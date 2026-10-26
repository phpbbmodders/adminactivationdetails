#!/usr/bin/env python3
"""Read captured test emails from one directory and emit decoded JSON."""
import email
import email.policy
import json
from pathlib import Path
import sys


def main() -> None:
    """Decode the given directory's synthetic SMTP captures to stdout."""
    if len(sys.argv) != 2 or sys.argv[1] in ("-h", "--help"):
        print("Usage: python3 read_mail.py MAIL_DIRECTORY")
        return
    messages = []
    for path in sorted(Path(sys.argv[1]).glob("*.eml")):
        message = email.message_from_bytes(
            path.read_bytes(), policy=email.policy.default
        )
        body = (
            message.get_body(preferencelist=("plain",))
            if message.is_multipart() else message
        )
        messages.append({
            "file": str(path), "to": str(message["To"]),
            "subject": str(message["Subject"]), "body": body.get_content(),
        })
    print(json.dumps(messages))


if __name__ == "__main__":
    main()
