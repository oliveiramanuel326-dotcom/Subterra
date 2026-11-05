#!/usr/bin/env python3

import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]

COLLECTIONS = {
    "publicacoes": {
        "icon": "article",
        "note": "Publicações da comunidade Subterra",
        "display_template": "{{ titulo }}",
        "hidden": False,
    },
    "comentarios": {
        "icon": "forum",
        "note": "Comentários das publicações",
        "display_template": "{{ conteudo }}",
        "hidden": False,
    },
    "mensagens": {
        "icon": "chat",
        "note": "Mensagens do chat público",
        "display_template": "{{ conteudo }}",
        "hidden": False,
    },
    "denuncias": {
        "icon": "flag",
        "note": "Denúncias de publicações",
        "display_template": "{{ motivo }}",
        "hidden": False,
    },
    "usuarios": {
        "hidden": True,
    },
    "votos": {
        "hidden": True,
    },
}


def load_env_file() -> None:
    env_file = ROOT / ".env.cms"
    if not env_file.is_file():
        return
    for line in env_file.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key, value)


def request(path: str, method: str = "GET", payload: dict | None = None,
            token: str | None = None) -> dict:
    body = None if payload is None else json.dumps(payload).encode("utf-8")
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    req = urllib.request.Request(
        f"{os.environ.get('SUBTERRA_CMS_URL', 'http://127.0.0.1:8055')}{path}",
        data=body,
        headers=headers,
        method=method,
    )
    with urllib.request.urlopen(req, timeout=15) as response:
        return json.load(response)


def main() -> int:
    load_env_file()
    email = os.environ.get("ADMIN_EMAIL")
    password = os.environ.get("ADMIN_PASSWORD")
    if not email or not password:
        print("ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env.cms.", file=sys.stderr)
        return 1

    last_error = None
    for _ in range(30):
        try:
            auth = request("/auth/login", "POST", {"email": email, "password": password})
            token = auth["data"]["access_token"]
            break
        except (OSError, KeyError, urllib.error.URLError) as error:
            last_error = error
            time.sleep(2)
    else:
        print(f"Could not authenticate with Directus: {last_error}", file=sys.stderr)
        return 1

    for name, metadata in COLLECTIONS.items():
        try:
            request(f"/collections/{name}", "PATCH", {"meta": metadata}, token)
        except urllib.error.HTTPError as error:
            detail = error.read().decode("utf-8", errors="replace")
            print(f"Could not register {name}: HTTP {error.code}: {detail}", file=sys.stderr)
            return 1

    print("Directus content collections are ready.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
