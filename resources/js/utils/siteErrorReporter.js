const CLIENT_ERROR_ENDPOINT = '/api/client-errors'
const RECENT_FINGERPRINT_WINDOW_MS = 15000
const recentFingerprints = new Map()

const trimText = (value, maxLength = 1000) => {
    const text = String(value ?? '').trim()
    return text.length > maxLength ? `${text.slice(0, maxLength)}...` : text
}

const normalizePath = (value) => {
    const raw = String(value ?? '').trim()
    if (raw === '' || typeof window === 'undefined') {
        return raw
    }

    try {
        return new URL(raw, window.location.origin).pathname
    } catch (_error) {
        return raw.split('?')[0] || raw
    }
}

const buildFingerprint = (payload) => {
    return [
        payload.kind,
        payload.message,
        payload.page_url,
        payload.request_url,
        payload.status_code,
        payload.source_file,
        payload.source_line,
        payload.route_name,
    ].join('|')
}

const shouldSendPayload = (payload) => {
    const now = Date.now()

    for (const [fingerprint, sentAt] of recentFingerprints.entries()) {
        if ((now - sentAt) > RECENT_FINGERPRINT_WINDOW_MS) {
            recentFingerprints.delete(fingerprint)
        }
    }

    const fingerprint = buildFingerprint(payload)
    const lastSentAt = recentFingerprints.get(fingerprint) ?? 0
    if ((now - lastSentAt) < RECENT_FINGERPRINT_WINDOW_MS) {
        return false
    }

    recentFingerprints.set(fingerprint, now)
    return true
}

const safeContext = (value, depth = 0) => {
    if (!value || typeof value !== 'object' || depth > 2) {
        return {}
    }

    const sourceEntries = Array.isArray(value) ? value.entries() : Object.entries(value)
    const result = {}

    for (const [rawKey, rawValue] of sourceEntries) {
        const key = trimText(rawKey, 64)
        if (key === '') {
            continue
        }

        if (rawValue === null || rawValue === undefined) {
            continue
        }

        if (typeof rawValue === 'boolean' || typeof rawValue === 'number') {
            result[key] = rawValue
            continue
        }

        if (typeof rawValue === 'string') {
            result[key] = trimText(rawValue, 500)
            continue
        }

        if (typeof rawValue === 'object') {
            const nested = safeContext(rawValue, depth + 1)
            if (Object.keys(nested).length > 0) {
                result[key] = nested
            }
        }
    }

    return result
}

const sendPayload = (payload) => {
    const body = JSON.stringify(payload)

    try {
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            const blob = new Blob([body], { type: 'application/json' })
            if (navigator.sendBeacon(CLIENT_ERROR_ENDPOINT, blob)) {
                return
            }
        }
    } catch (_error) {
        // Fall back to fetch below.
    }

    try {
        fetch(CLIENT_ERROR_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            keepalive: true,
            body,
        }).catch(() => {})
    } catch (_error) {
        // Ignore secondary failures while reporting client errors.
    }
}

export const reportClientError = (payload = {}) => {
    if (typeof window === 'undefined') {
        return
    }

    const normalizedPayload = {
        kind: trimText(payload.kind || 'runtime', 16) || 'runtime',
        message: trimText(payload.message || 'Unknown client error', 4000),
        stack: trimText(payload.stack || '', 30000),
        page_url: trimText(payload.page_url || window.location.href, 2048),
        route_name: trimText(payload.route_name || '', 120),
        request_url: trimText(payload.request_url || '', 2048),
        request_method: trimText(payload.request_method || '', 16).toUpperCase(),
        status_code: Number.isFinite(Number(payload.status_code)) ? Number(payload.status_code) : null,
        source_file: trimText(payload.source_file || '', 2048),
        source_line: Number.isFinite(Number(payload.source_line)) ? Number(payload.source_line) : null,
        source_column: Number.isFinite(Number(payload.source_column)) ? Number(payload.source_column) : null,
        context: safeContext(payload.context),
    }

    if (normalizedPayload.message === '' || !shouldSendPayload(normalizedPayload)) {
        return
    }

    sendPayload(normalizedPayload)
}

export const installClientErrorReporter = ({ app, router, axios }) => {
    if (typeof window === 'undefined') {
        return
    }

    const currentRouteName = () => trimText(router?.currentRoute?.value?.name ?? '', 120)

    const previousErrorHandler = app?.config?.errorHandler
    if (app?.config) {
        app.config.errorHandler = (error, instance, info) => {
            reportClientError({
                kind: 'vue',
                message: error?.message || String(error || 'Vue runtime error'),
                stack: error?.stack || '',
                route_name: currentRouteName(),
                context: {
                    info: trimText(info, 500),
                    component: trimText(instance?.$options?.name ?? '', 120),
                },
            })

            if (typeof previousErrorHandler === 'function') {
                previousErrorHandler(error, instance, info)
                return
            }

            console.error(error)
        }
    }

    window.addEventListener('error', (event) => {
        const error = event?.error
        const message = error?.message || event?.message

        if (trimText(message, 4000) === '') {
            return
        }

        reportClientError({
            kind: 'runtime',
            message,
            stack: error?.stack || '',
            source_file: event?.filename || '',
            source_line: event?.lineno,
            source_column: event?.colno,
            route_name: currentRouteName(),
        })
    })

    window.addEventListener('unhandledrejection', (event) => {
        const reason = event?.reason
        const reasonMessage = reason?.message || trimText(reason, 4000) || 'Unhandled promise rejection'

        reportClientError({
            kind: 'promise',
            message: reasonMessage,
            stack: reason?.stack || '',
            route_name: currentRouteName(),
            context: {
                reason_type: trimText(reason?.constructor?.name ?? typeof reason, 120),
            },
        })
    })

    if (axios?.interceptors?.response) {
        axios.interceptors.response.use(
            (response) => response,
            (error) => {
                const statusCode = Number(error?.response?.status || 0)
                const requestUrl = trimText(error?.config?.url || '', 2048)

                if (
                    normalizePath(requestUrl) !== CLIENT_ERROR_ENDPOINT
                    && (statusCode >= 500 || (!statusCode && !error?.response))
                ) {
                    reportClientError({
                        kind: 'http',
                        message: trimText(error?.message || 'HTTP request failed', 4000),
                        stack: error?.stack || '',
                        route_name: currentRouteName(),
                        request_url: requestUrl,
                        request_method: trimText(error?.config?.method || '', 16),
                        status_code: statusCode || null,
                        context: {
                            response_message: trimText(error?.response?.data?.message || '', 500),
                            network_error: !statusCode,
                        },
                    })
                }

                return Promise.reject(error)
            }
        )
    }
}
