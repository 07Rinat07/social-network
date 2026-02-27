const STICKER_IMAGE_BASE = '/stickers/twemoji'
const STICKER_EMOJI_BY_ID = {
    wave: '👋',
    hug: '🤗',
    handshake: '🤝',
    salute: '🫡',
    call_me: '🤙',

    smile: '😀',
    grin: '😁',
    tears_joy: '😂',
    party_face: '🥳',
    cool: '😎',

    heart_eyes: '😍',
    red_heart: '❤️',
    sparkling_heart: '💖',
    kissing_heart: '😘',
    couple: '🧑‍🤝‍🧑',

    thumbs_up: '👍',
    clapping: '👏',
    muscles: '💪',
    fire: '🔥',
    star: '⭐',

    cry: '😢',
    sob: '😭',
    pensive: '😔',
    angry: '😡',
    facepalm: '🤦',

    laptop: '💻',
    books: '📚',
    lightbulb: '💡',
    rocket: '🚀',
    target: '🎯',

    pizza: '🍕',
    coffee: '☕',
    birthday_cake: '🎂',
    popcorn: '🍿',
    burger: '🍔',

    airplane: '✈️',
    car: '🚗',
    beach: '🏖️',
    mountain: '🏔️',
    tree: '🌳',

    cat: '🐱',
    dog: '🐶',
    panda: '🐼',
    unicorn: '🦄',
    tiger: '🐯',

    sun: '☀️',
    moon: '🌙',
    rainbow: '🌈',
    snowflake: '❄️',
    thunder: '⚡',
}

export const STICKER_CATEGORIES = [
    { id: 'all', labels: { ru: 'Все', en: 'All' } },
    { id: 'greetings', labels: { ru: 'Приветствия', en: 'Greetings' } },
    { id: 'joy', labels: { ru: 'Радость', en: 'Joy' } },
    { id: 'love', labels: { ru: 'Любовь', en: 'Love' } },
    { id: 'support', labels: { ru: 'Поддержка', en: 'Support' } },
    { id: 'mood', labels: { ru: 'Настроение', en: 'Mood' } },
    { id: 'work', labels: { ru: 'Работа', en: 'Work' } },
    { id: 'food', labels: { ru: 'Еда', en: 'Food' } },
    { id: 'travel', labels: { ru: 'Путешествия', en: 'Travel' } },
    { id: 'animals', labels: { ru: 'Животные', en: 'Animals' } },
    { id: 'weather', labels: { ru: 'Погода', en: 'Weather' } },
]

export const STICKER_CATALOG = [
    { id: 'wave', category: 'greetings', labels: { ru: 'Привет', en: 'Wave' } },
    { id: 'hug', category: 'greetings', labels: { ru: 'Обнимашки', en: 'Hug' } },
    { id: 'handshake', category: 'greetings', labels: { ru: 'Рукопожатие', en: 'Handshake' } },
    { id: 'salute', category: 'greetings', labels: { ru: 'Салют', en: 'Salute' } },
    { id: 'call_me', category: 'greetings', labels: { ru: 'Позвони', en: 'Call me' } },

    { id: 'smile', category: 'joy', labels: { ru: 'Улыбка', en: 'Smile' } },
    { id: 'grin', category: 'joy', labels: { ru: 'Смех', en: 'Grin' } },
    { id: 'tears_joy', category: 'joy', labels: { ru: 'Смех до слез', en: 'Tears of joy' } },
    { id: 'party_face', category: 'joy', labels: { ru: 'Праздник', en: 'Party' } },
    { id: 'cool', category: 'joy', labels: { ru: 'Круто', en: 'Cool' } },

    { id: 'heart_eyes', category: 'love', labels: { ru: 'Влюблен', en: 'Heart eyes' } },
    { id: 'red_heart', category: 'love', labels: { ru: 'Сердце', en: 'Heart' } },
    { id: 'sparkling_heart', category: 'love', labels: { ru: 'Любовь', en: 'Sparkling heart' } },
    { id: 'kissing_heart', category: 'love', labels: { ru: 'Поцелуй', en: 'Kiss' } },
    { id: 'couple', category: 'love', labels: { ru: 'Пара', en: 'Couple' } },

    { id: 'thumbs_up', category: 'support', labels: { ru: 'Лайк', en: 'Thumbs up' } },
    { id: 'clapping', category: 'support', labels: { ru: 'Аплодисменты', en: 'Clap' } },
    { id: 'muscles', category: 'support', labels: { ru: 'Сила', en: 'Muscle' } },
    { id: 'fire', category: 'support', labels: { ru: 'Огонь', en: 'Fire' } },
    { id: 'star', category: 'support', labels: { ru: 'Звезда', en: 'Star' } },

    { id: 'cry', category: 'mood', labels: { ru: 'Грусть', en: 'Cry' } },
    { id: 'sob', category: 'mood', labels: { ru: 'Плачу', en: 'Sob' } },
    { id: 'pensive', category: 'mood', labels: { ru: 'Задумчиво', en: 'Pensive' } },
    { id: 'angry', category: 'mood', labels: { ru: 'Злость', en: 'Angry' } },
    { id: 'facepalm', category: 'mood', labels: { ru: 'Фейспалм', en: 'Facepalm' } },

    { id: 'laptop', category: 'work', labels: { ru: 'Ноутбук', en: 'Laptop' } },
    { id: 'books', category: 'work', labels: { ru: 'Книги', en: 'Books' } },
    { id: 'lightbulb', category: 'work', labels: { ru: 'Идея', en: 'Idea' } },
    { id: 'rocket', category: 'work', labels: { ru: 'Запуск', en: 'Launch' } },
    { id: 'target', category: 'work', labels: { ru: 'Цель', en: 'Target' } },

    { id: 'pizza', category: 'food', labels: { ru: 'Пицца', en: 'Pizza' } },
    { id: 'coffee', category: 'food', labels: { ru: 'Кофе', en: 'Coffee' } },
    { id: 'birthday_cake', category: 'food', labels: { ru: 'Торт', en: 'Cake' } },
    { id: 'popcorn', category: 'food', labels: { ru: 'Попкорн', en: 'Popcorn' } },
    { id: 'burger', category: 'food', labels: { ru: 'Бургер', en: 'Burger' } },

    { id: 'airplane', category: 'travel', labels: { ru: 'Самолет', en: 'Airplane' } },
    { id: 'car', category: 'travel', labels: { ru: 'Машина', en: 'Car' } },
    { id: 'beach', category: 'travel', labels: { ru: 'Пляж', en: 'Beach' } },
    { id: 'mountain', category: 'travel', labels: { ru: 'Горы', en: 'Mountain' } },
    { id: 'tree', category: 'travel', labels: { ru: 'Природа', en: 'Tree' } },

    { id: 'cat', category: 'animals', labels: { ru: 'Кот', en: 'Cat' } },
    { id: 'dog', category: 'animals', labels: { ru: 'Пес', en: 'Dog' } },
    { id: 'panda', category: 'animals', labels: { ru: 'Панда', en: 'Panda' } },
    { id: 'unicorn', category: 'animals', labels: { ru: 'Единорог', en: 'Unicorn' } },
    { id: 'tiger', category: 'animals', labels: { ru: 'Тигр', en: 'Tiger' } },

    { id: 'sun', category: 'weather', labels: { ru: 'Солнце', en: 'Sun' } },
    { id: 'moon', category: 'weather', labels: { ru: 'Луна', en: 'Moon' } },
    { id: 'rainbow', category: 'weather', labels: { ru: 'Радуга', en: 'Rainbow' } },
    { id: 'snowflake', category: 'weather', labels: { ru: 'Снег', en: 'Snowflake' } },
    { id: 'thunder', category: 'weather', labels: { ru: 'Молния', en: 'Thunder' } },
].map((sticker) => ({
    ...sticker,
    emoji: STICKER_EMOJI_BY_ID[sticker.id] || '🧩',
    src: `${STICKER_IMAGE_BASE}/${sticker.id}.png`,
    token: `[sticker:${sticker.id}]`,
}))

export const STICKER_BY_ID = new Map(STICKER_CATALOG.map((sticker) => [sticker.id, sticker]))
const STICKER_TOKEN_RE = /\[sticker:([a-z0-9_]+)\]/gi
const STICKER_INLINE_MARKER = '\u2063'

export function getStickerById(id) {
    const stickerId = String(id || '').trim().toLowerCase()
    return STICKER_BY_ID.get(stickerId) || null
}

export function localizedStickerLabel(sticker, locale = 'ru') {
    if (!sticker || typeof sticker !== 'object') {
        return ''
    }

    const safeLocale = String(locale || 'ru').toLowerCase().startsWith('en') ? 'en' : 'ru'
    const labels = sticker.labels || {}
    return String(labels[safeLocale] || labels.ru || labels.en || sticker.id || '').trim()
}

export function localizedCategoryLabel(category, locale = 'ru') {
    const safeLocale = String(locale || 'ru').toLowerCase().startsWith('en') ? 'en' : 'ru'
    const labels = category?.labels || {}
    return String(labels[safeLocale] || labels.ru || labels.en || category?.id || '').trim()
}

export function stickerTokenFromId(id) {
    const sticker = getStickerById(id)
    return sticker ? sticker.token : ''
}

export function stickerEmojiFromId(id, fallback = '🧩') {
    const sticker = getStickerById(id)
    return sticker?.emoji || fallback
}

export function stickerMarkedEmojiFromId(id, fallback = '🧩') {
    const sticker = getStickerById(id)
    if (!sticker) {
        return ''
    }

    return `${STICKER_INLINE_MARKER}${sticker.emoji || fallback}`
}

export function replaceStickerTokensWithEmoji(text, fallback = '🧩') {
    const source = String(text || '')
    if (source === '') {
        return ''
    }

    STICKER_TOKEN_RE.lastIndex = 0
    return source.replace(STICKER_TOKEN_RE, (_match, stickerId) => stickerEmojiFromId(stickerId, fallback))
}

export function replaceStickerTokensWithMarkedEmoji(text, fallback = '🧩') {
    const source = String(text || '')
    if (source === '') {
        return ''
    }

    STICKER_TOKEN_RE.lastIndex = 0
    return source.replace(STICKER_TOKEN_RE, (_match, stickerId) => stickerMarkedEmojiFromId(stickerId, fallback))
}

export function replaceMarkedEmojiWithStickerTokens(text) {
    let source = String(text || '')
    if (source === '') {
        return ''
    }

    for (const sticker of STICKER_CATALOG) {
        const emoji = String(sticker?.emoji || '')
        if (emoji === '') {
            continue
        }

        source = source.split(`${STICKER_INLINE_MARKER}${emoji}`).join(sticker.token)
    }

    return source.split(STICKER_INLINE_MARKER).join('')
}

export function parseStickerTextSegments(text) {
    const source = String(text || '')
    if (source === '') {
        return []
    }

    const segments = []
    let lastIndex = 0
    let match
    STICKER_TOKEN_RE.lastIndex = 0

    while ((match = STICKER_TOKEN_RE.exec(source)) !== null) {
        const matchIndex = Number(match.index || 0)
        if (matchIndex > lastIndex) {
            segments.push({
                type: 'text',
                value: source.slice(lastIndex, matchIndex),
            })
        }

        const sticker = getStickerById(match[1])
        if (sticker) {
            segments.push({
                type: 'sticker',
                sticker,
            })
        } else {
            segments.push({
                type: 'text',
                value: String(match[0] || ''),
            })
        }

        lastIndex = matchIndex + String(match[0] || '').length
    }

    if (lastIndex < source.length) {
        segments.push({
            type: 'text',
            value: source.slice(lastIndex),
        })
    }

    return segments
}

export function stickerTextToPreview(text, placeholder = '🧩') {
    const source = String(text || '')
    if (source === '') {
        return ''
    }

    STICKER_TOKEN_RE.lastIndex = 0
    return source
        .replace(STICKER_TOKEN_RE, ` ${placeholder} `)
        .replace(/\s+/g, ' ')
        .trim()
}

export default STICKER_CATALOG
