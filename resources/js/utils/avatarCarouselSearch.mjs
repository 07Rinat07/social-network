function normalizeLocale(locale) {
    return String(locale || '').toLowerCase().startsWith('en') ? 'en-US' : 'ru-RU'
}

export function normalizeAvatarSearchValue(value, locale = 'ru-RU') {
    return String(value || '')
        .trim()
        .replace(/^@+/u, '')
        .replace(/\s+/gu, ' ')
        .toLocaleLowerCase(normalizeLocale(locale))
}

function normalizeAvatarSearchLooseValue(value, locale = 'ru-RU') {
    // Normalize common separators and letter-digit boundaries so "user6", "user 6" and "user_6"
    // land in the same loose-search bucket.
    return normalizeAvatarSearchValue(value, locale)
        .replace(/[_\-.]+/gu, ' ')
        .replace(/([\p{L}])(\d)/gu, '$1 $2')
        .replace(/(\d)([\p{L}])/gu, '$1 $2')
        .replace(/\s+/gu, ' ')
        .trim()
}

function buildAvatarSearchFields(entry, locale = 'ru-RU') {
    return [
        entry?.display_name,
        entry?.name,
        entry?.nickname,
    ]
        .map((value) => normalizeAvatarSearchValue(value, locale))
        .filter((value) => value !== '')
}

function buildAvatarSearchLooseFields(entry, locale = 'ru-RU') {
    return [
        entry?.display_name,
        entry?.name,
        entry?.nickname,
    ]
        .map((value) => normalizeAvatarSearchLooseValue(value, locale))
        .filter((value) => value !== '')
}

function escapeRegex(value) {
    return String(value || '').replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')
}

function splitSearchTokens(value) {
    return String(value || '').trim().split(/\s+/u).filter((token) => token !== '')
}

function isDigitsOnly(value) {
    return /^\d+$/u.test(String(value || ''))
}

function tokenMatches(fieldToken, queryToken) {
    if (fieldToken === queryToken) {
        return true
    }

    if (isDigitsOnly(queryToken)) {
        return false
    }

    return fieldToken.startsWith(queryToken)
}

function matchesSimilar(looseFields, looseQuery) {
    if (!Array.isArray(looseFields) || looseFields.length === 0 || looseQuery === '') {
        return false
    }

    const tokens = splitSearchTokens(looseQuery)
    if (tokens.length === 0) {
        return false
    }

    const pattern = tokens
        .map((token) => escapeRegex(token))
        .join('[\\s._-]*')
    const matcher = new RegExp(`${pattern}(?=$|[^\\p{L}\\d])`, 'u')

    // First try a compact regex that tolerates separators; if it misses, fall back to token-level
    // prefix matching so partial queries can still surface multiple close variants.
    if (looseFields.some((value) => matcher.test(value))) {
        return true
    }

    return looseFields.some((field) => {
        const fieldTokens = splitSearchTokens(field)
        return tokens.every((token) => fieldTokens.some((fieldToken) => tokenMatches(fieldToken, token)))
    })
}

export function resolveAvatarCarouselSearch(users, query, locale = 'ru-RU') {
    const entries = Array.isArray(users) ? users : []
    const normalizedQuery = normalizeAvatarSearchValue(query, locale)

    if (normalizedQuery === '') {
        return {
            mode: 'all',
            items: entries,
            query: normalizedQuery,
        }
    }

    const looseQuery = normalizeAvatarSearchLooseValue(query, locale)
    const exactMatches = entries.filter((entry) => {
        const strictFields = buildAvatarSearchFields(entry, locale)
        if (strictFields.some((value) => value === normalizedQuery)) {
            return true
        }

        // Loose equality handles separator-only differences without degrading into broad "similar" mode.
        return buildAvatarSearchLooseFields(entry, locale).some((value) => value === looseQuery)
    })

    if (exactMatches.length > 0) {
        return {
            mode: 'exact',
            items: exactMatches,
            query: normalizedQuery,
        }
    }

    const similarMatches = entries.filter((entry) => matchesSimilar(buildAvatarSearchLooseFields(entry, locale), looseQuery))

    if (similarMatches.length > 0) {
        return {
            mode: 'similar',
            items: similarMatches,
            query: normalizedQuery,
        }
    }

    return {
        mode: 'none',
        items: [],
        query: normalizedQuery,
    }
}
