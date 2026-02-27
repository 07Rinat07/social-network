const escapeSvgText = (value) => {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
}

const buildFallbackSvg = (label) => {
    const normalizedLabel = escapeSvgText(label || 'Preview unavailable')

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 960 540">
        <defs>
            <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#f6efe2"/>
                <stop offset="100%" stop-color="#eadfc9"/>
            </linearGradient>
        </defs>
        <rect width="960" height="540" fill="url(#g)"/>
        <rect x="286" y="150" width="388" height="220" rx="16" fill="none" stroke="#b8a78a" stroke-width="14"/>
        <circle cx="380" cy="220" r="26" fill="#b8a78a"/>
        <path d="M340 336 L430 260 L500 316 L560 274 L620 336 Z" fill="#b8a78a"/>
        <text x="480" y="408" text-anchor="middle" font-size="30" font-family="Arial, sans-serif" fill="#6e6258">${normalizedLabel}</text>
</svg>`
}

const isDataUrl = (value) => {
    return String(value || '').trim().startsWith('data:')
}

const appendRetryQuery = (url, attempt) => {
    const stamp = `${Date.now()}-${attempt}`

    try {
        const parsed = new URL(url, window.location.origin)
        parsed.searchParams.set('_img_retry', stamp)
        return parsed.toString()
    } catch (_error) {
        const separator = String(url).includes('?') ? '&' : '?'
        return `${url}${separator}_img_retry=${stamp}`
    }
}

export const applyImagePreviewFallback = (event, label = 'Preview unavailable') => {
    const image = event?.target
    if (!(image instanceof HTMLImageElement)) {
        return
    }

    const source = String(
        image.getAttribute('src')
        || image.currentSrc
        || image.src
        || ''
    ).trim()

    if (image.dataset.originalSrc === undefined && source !== '' && !isDataUrl(source)) {
        image.dataset.originalSrc = source
    }

    const original = String(image.dataset.originalSrc || source).trim()
    const retryCount = Number(image.dataset.fallbackRetryCount || '0')

    // The first error can be a transient cancellation (navigation/rerender).
    // Retry once with a cache-busting query before showing a hard fallback.
    if (retryCount < 1 && original !== '' && !isDataUrl(original)) {
        image.dataset.fallbackRetryCount = String(retryCount + 1)
        image.src = appendRetryQuery(original, retryCount + 1)
        return
    }

    if (image.dataset.fallbackApplied === '1') {
        return
    }

    image.dataset.fallbackApplied = '1'
    image.src = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(buildFallbackSvg(label))}`
    image.alt = label
}

export const resetImagePreviewFallback = (event) => {
    const image = event?.target
    if (!(image instanceof HTMLImageElement)) {
        return
    }

    const source = String(
        image.getAttribute('src')
        || image.currentSrc
        || image.src
        || ''
    ).trim()

    if (isDataUrl(source)) {
        return
    }

    delete image.dataset.fallbackApplied
    delete image.dataset.fallbackRetryCount
}
