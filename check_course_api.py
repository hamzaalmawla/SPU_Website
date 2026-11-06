#!/usr/bin/env python3
"""Verify access to an authenticated SPU API database-status endpoint."""

from __future__ import annotations

import json
import os
import sys
from typing import Any
from urllib.parse import urljoin, urlparse

import requests
from dotenv import load_dotenv
from requests import Response
from requests.exceptions import RequestException, SSLError, Timeout


HEALTHY_DATABASE_STATES = {"connected", "healthy", "ok", "ready", "up"}
UNHEALTHY_DATABASE_STATES = {
    "disconnected",
    "down",
    "error",
    "failed",
    "unhealthy",
    "unavailable",
}


def required_env(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise ValueError(f"Missing required environment variable: {name}")
    if "\r" in value or "\n" in value:
        raise ValueError(f"Invalid newline in environment variable: {name}")
    return value


def database_state(payload: Any) -> bool | None:
    """Return database health when the API payload exposes a recognizable state."""
    if not isinstance(payload, dict):
        return None

    value = next(
        (
            payload[key]
            for key in ("database", "database_status", "db", "db_status")
            if key in payload
        ),
        None,
    )

    if isinstance(value, dict):
        value = next(
            (value[key] for key in ("connected", "healthy", "status") if key in value),
            None,
        )

    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        normalized = value.strip().lower()
        if normalized in HEALTHY_DATABASE_STATES:
            return True
        if normalized in UNHEALTHY_DATABASE_STATES:
            return False

    return None


def response_payload(response: Response) -> Any:
    content_type = response.headers.get("Content-Type", "").lower()
    if "json" not in content_type:
        return None

    try:
        return response.json()
    except requests.exceptions.JSONDecodeError:
        return None


def main() -> int:
    load_dotenv(override=False)

    try:
        base_url = required_env("SPU_API_BASE_URL")
        status_path = required_env("SPU_API_STATUS_PATH")
        token = required_env("SPU_API_TOKEN")
        timeout = float(os.getenv("SPU_API_TIMEOUT", "15"))
    except (ValueError, TypeError) as error:
        print(f"Configuration error: {error}", file=sys.stderr)
        return 2

    parsed_base_url = urlparse(base_url)
    if parsed_base_url.scheme != "https" or not parsed_base_url.netloc:
        print("Configuration error: SPU_API_BASE_URL must be a valid HTTPS URL.", file=sys.stderr)
        return 2
    if not status_path.startswith("/") or status_path.startswith("//"):
        print("Configuration error: SPU_API_STATUS_PATH must start with one '/'.", file=sys.stderr)
        return 2
    if timeout <= 0:
        print("Configuration error: SPU_API_TIMEOUT must be greater than zero.", file=sys.stderr)
        return 2

    url = urljoin(base_url.rstrip("/") + "/", status_path.lstrip("/"))
    headers = {
        "Accept": "application/json",
        "Authorization": f"Bearer {token}",
        "User-Agent": "SPU-course-api-connection-check/1.0",
    }

    try:
        response = requests.get(url, headers=headers, timeout=timeout)
        response.raise_for_status()
    except Timeout:
        print(f"Connection failed: request timed out after {timeout:g} seconds.", file=sys.stderr)
        return 1
    except SSLError as error:
        print(f"Connection failed: TLS certificate verification failed: {error}", file=sys.stderr)
        return 1
    except RequestException as error:
        status = error.response.status_code if error.response is not None else None
        detail = f"HTTP {status}" if status is not None else str(error)
        print(f"Connection failed: {detail}", file=sys.stderr)
        return 1

    payload = response_payload(response)
    state = database_state(payload)

    print(f"API connection successful: HTTP {response.status_code} from {url}")
    if state is True:
        print("Database status: connected/healthy")
        return 0
    if state is False:
        print("Database status: unhealthy/disconnected", file=sys.stderr)
        return 1

    print(
        "Database status: not reported by this endpoint. "
        "Use a protected endpoint whose JSON includes database, database_status, db, or db_status.",
        file=sys.stderr,
    )
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
