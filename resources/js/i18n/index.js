import {inject, ref, watch} from 'vue'
import en from './messages/en'
import ru from './messages/ru'
import {runtimeTextMap, runtimeTextPatterns} from './runtimeTextMap'

export const SUPPORTED_LOCALES = ['ru', 'en']
export const DEFAULT_LOCALE = 'ru'
const LOCALE_STORAGE_KEY = 'solid-social:locale'
const TRANSLATABLE_ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt']
const TRANSLATABLE_INPUT_TYPES = new Set(['button', 'submit', 'reset'])
const SKIPPED_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT'])
const CYRILLIC_REGEXP = /[А-Яа-яЁё]/

const messages = {
    ru,
    en,
}

const locale = ref(DEFAULT_LOCALE)
const runtimeReplacementEntries = Object.entries(runtimeTextMap)
    .sort((first, second) => second[0].length - first[0].length)

const runtimeTextNodeOriginal = new WeakMap()
const runtimeAttributeOriginal = new WeakMap()
const runtimeInputValueOriginal = new WeakMap()

let runtimeTranslationStarted = false
let runtimeTranslationQueued = false
let runtimeObserver = null
let runtimeObserverConnected = false
let runtimeDialogsPatched = false
let runtimeLocaleWatchStop = null

function resolveMessageByKey(source, key) {
    if (!source || typeof source !== 'object' || typeof key !== 'string' || key.trim() === '') {
        return null
    }

    return key.split('.').reduce((cursor, part) => {
        if (!cursor || typeof cursor !== 'object') {
            return null
        }

        return Object.prototype.hasOwnProperty.call(cursor, part) ? cursor[part] : null
    }, source)
}

function interpolate(template, params = {}) {
    return template.replace(/\{(\w+)\}/g, (_, key) => {
        if (!Object.prototype.hasOwnProperty.call(params, key)) {
            return `{${key}}`
        }

        const value = params[key]
        return value === null || value === undefined ? '' : String(value)
    })
}

function shouldSkipElement(element) {
    if (!element || element.nodeType !== 1) {
        return true
    }

    if (SKIPPED_TAGS.has(element.tagName)) {
        return true
    }

    return element.hasAttribute('data-no-runtime-i18n')
}

function translateExactOrPatternText(source) {
    if (Object.prototype.hasOwnProperty.call(runtimeTextMap, source)) {
        return runtimeTextMap[source]
    }

    for (const [pattern, replacement] of runtimeTextPatterns) {
        if (pattern.test(source)) {
            return source.replace(pattern, replacement)
        }
    }

    return null
}

function translateByPartialMatches(source) {
    let translated = source
    let changed = false

    for (const [from, to] of runtimeReplacementEntries) {
        if (from.length < 4 || !translated.includes(from)) {
            continue
        }

        translated = translated.split(from).join(to)
        changed = true
    }

    return changed ? translated : null
}

export function translateRuntimeText(value, targetLocale = locale.value) {
    const source = value === null || value === undefined ? '' : String(value)
    if (source === '' || targetLocale !== 'en') {
        return source
    }

    if (Object.prototype.hasOwnProperty.call(runtimeTextMap, source)) {
        return runtimeTextMap[source]
    }

    const trimmedSource = source.trim()
    if (trimmedSource === '' || !CYRILLIC_REGEXP.test(trimmedSource)) {
        return source
    }

    const translatedCore =
        translateExactOrPatternText(trimmedSource)
        ?? translateByPartialMatches(trimmedSource)

    if (typeof translatedCore !== 'string' || translatedCore.length === 0) {
        return source
    }

    if (trimmedSource === source) {
        return translatedCore
    }

    const startIndex = source.indexOf(trimmedSource)
    if (startIndex < 0) {
        return translatedCore
    }

    const endIndex = startIndex + trimmedSource.length
    return `${source.slice(0, startIndex)}${translatedCore}${source.slice(endIndex)}`
}

function getOriginalTextNodeValue(node) {
    const currentValue = node.nodeValue ?? ''
    if (!runtimeTextNodeOriginal.has(node)) {
        runtimeTextNodeOriginal.set(node, currentValue)
        return currentValue
    }

    const cachedValue = runtimeTextNodeOriginal.get(node)
    if (locale.value === DEFAULT_LOCALE) {
        runtimeTextNodeOriginal.set(node, currentValue)
        return currentValue
    }

    const cachedTranslatedValue = translateRuntimeText(cachedValue, 'en')
    if (CYRILLIC_REGEXP.test(currentValue) && currentValue !== cachedTranslatedValue) {
        runtimeTextNodeOriginal.set(node, currentValue)
        return currentValue
    }

    return cachedValue
}

function getAttributeStore(element) {
    let store = runtimeAttributeOriginal.get(element)
    if (!store) {
        store = new Map()
        runtimeAttributeOriginal.set(element, store)
    }

    return store
}

function getOriginalAttributeValue(element, attributeName, currentValue) {
    const attributeStore = getAttributeStore(element)
    if (!attributeStore.has(attributeName)) {
        attributeStore.set(attributeName, currentValue)
        return currentValue
    }

    const cachedValue = attributeStore.get(attributeName)
    if (locale.value === DEFAULT_LOCALE) {
        attributeStore.set(attributeName, currentValue)
        return currentValue
    }

    const cachedTranslatedValue = translateRuntimeText(cachedValue, 'en')
    if (CYRILLIC_REGEXP.test(currentValue) && currentValue !== cachedTranslatedValue) {
        attributeStore.set(attributeName, currentValue)
        return currentValue
    }

    return cachedValue
}

function getOriginalInputValue(element, currentValue) {
    if (!runtimeInputValueOriginal.has(element)) {
        runtimeInputValueOriginal.set(element, currentValue)
        return currentValue
    }

    const cachedValue = runtimeInputValueOriginal.get(element)
    if (locale.value === DEFAULT_LOCALE) {
        runtimeInputValueOriginal.set(element, currentValue)
        return currentValue
    }

    const cachedTranslatedValue = translateRuntimeText(cachedValue, 'en')
    if (CYRILLIC_REGEXP.test(currentValue) && currentValue !== cachedTranslatedValue) {
        runtimeInputValueOriginal.set(element, currentValue)
        return currentValue
    }

    return cachedValue
}

function translateTextNode(node) {
    if (!node || node.nodeType !== 3) {
        return
    }

    const parent = node.parentElement
    if (!parent || shouldSkipElement(parent)) {
        return
    }

    const sourceText = getOriginalTextNodeValue(node)
    if (sourceText.trim() === '') {
        return
    }

    const translatedText = locale.value === 'en'
        ? translateRuntimeText(sourceText, 'en')
        : sourceText

    if (node.nodeValue !== translatedText) {
        node.nodeValue = translatedText
    }
}

function translateElementAttributes(element) {
    if (!element || shouldSkipElement(element)) {
        return
    }

    for (const attribute of TRANSLATABLE_ATTRIBUTES) {
        if (!element.hasAttribute(attribute)) {
            continue
        }

        const currentValue = element.getAttribute(attribute) ?? ''
        if (currentValue === '') {
            continue
        }

        const sourceValue = getOriginalAttributeValue(element, attribute, currentValue)
        const translatedValue = locale.value === 'en'
            ? translateRuntimeText(sourceValue, 'en')
            : sourceValue

        if (translatedValue !== currentValue) {
            element.setAttribute(attribute, translatedValue)
        }
    }
}

function translateInputValue(element) {
    if (!element || element.tagName !== 'INPUT') {
        return
    }

    const inputType = String(element.getAttribute('type') || '').toLowerCase()
    if (!TRANSLATABLE_INPUT_TYPES.has(inputType)) {
        return
    }

    const currentValue = element.value ?? ''
    if (currentValue === '') {
        return
    }

    const sourceValue = getOriginalInputValue(element, currentValue)
    const translatedValue = locale.value === 'en'
        ? translateRuntimeText(sourceValue, 'en')
        : sourceValue

    if (translatedValue !== currentValue) {
        element.value = translatedValue
    }
}

function translateRuntimeSubtree(rootNode) {
    if (typeof document === 'undefined') {
        return
    }

    const startNode = rootNode || document.body
    if (!startNode) {
        return
    }

    const stack = [startNode]
    while (stack.length > 0) {
        const node = stack.pop()
        if (!node) {
            continue
        }

        if (node.nodeType === 3) {
            translateTextNode(node)
            continue
        }

        if (node.nodeType !== 1 && node.nodeType !== 9 && node.nodeType !== 11) {
            continue
        }

        if (node.nodeType === 1) {
            if (shouldSkipElement(node)) {
                continue
            }

            translateElementAttributes(node)
            translateInputValue(node)
        }

        const childNodes = node.childNodes || []
        for (let index = childNodes.length - 1; index >= 0; index -= 1) {
            stack.push(childNodes[index])
        }
    }
}

function handleRuntimeMutations(mutations) {
    for (const mutation of mutations) {
        if (mutation.type === 'characterData') {
            translateTextNode(mutation.target)
            continue
        }

        if (mutation.type === 'attributes') {
            const targetElement = mutation.target
            if (!targetElement || targetElement.nodeType !== 1 || shouldSkipElement(targetElement)) {
                continue
            }

            translateElementAttributes(targetElement)
            translateInputValue(targetElement)
            continue
        }

        if (mutation.type === 'childList') {
            for (const addedNode of mutation.addedNodes) {
                translateRuntimeSubtree(addedNode)
            }
        }
    }
}

function connectRuntimeObserver() {
    if (
        runtimeObserverConnected
        || typeof MutationObserver === 'undefined'
        || typeof document === 'undefined'
        || !document.body
    ) {
        return
    }

    runtimeObserver = new MutationObserver(handleRuntimeMutations)
    runtimeObserver.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: [...TRANSLATABLE_ATTRIBUTES, 'value'],
    })
    runtimeObserverConnected = true
}

function scheduleRuntimeTranslation() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return
    }

    if (runtimeTranslationQueued) {
        return
    }

    runtimeTranslationQueued = true
    const run = () => {
        runtimeTranslationQueued = false
        translateRuntimeSubtree(document.body)
    }

    if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(run)
        return
    }

    window.setTimeout(run, 16)
}

function patchBrowserDialogs() {
    if (runtimeDialogsPatched || typeof window === 'undefined') {
        return
    }

    runtimeDialogsPatched = true

    const originalAlert = typeof window.alert === 'function' ? window.alert.bind(window) : null
    const originalConfirm = typeof window.confirm === 'function' ? window.confirm.bind(window) : null
    const originalPrompt = typeof window.prompt === 'function' ? window.prompt.bind(window) : null

    if (originalAlert) {
        window.alert = (message) => originalAlert(translateRuntimeText(message, locale.value))
    }

    if (originalConfirm) {
        window.confirm = (message) => originalConfirm(translateRuntimeText(message, locale.value))
    }

    if (originalPrompt) {
        window.prompt = (message, defaultValue = '') => originalPrompt(
            translateRuntimeText(message, locale.value),
            defaultValue
        )
    }
}

function startRuntimeTranslation() {
    if (runtimeTranslationStarted || typeof window === 'undefined' || typeof document === 'undefined') {
        return
    }

    runtimeTranslationStarted = true

    if (!runtimeLocaleWatchStop) {
        runtimeLocaleWatchStop = watch(locale, () => {
            scheduleRuntimeTranslation()
        })
    }

    patchBrowserDialogs()

    const bootstrap = () => {
        connectRuntimeObserver()
        scheduleRuntimeTranslation()
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, {once: true})
        return
    }

    bootstrap()
}

export function normalizeLocale(value) {
    const normalized = String(value || '').trim().toLowerCase()
    return SUPPORTED_LOCALES.includes(normalized) ? normalized : null
}

export function getLocaleFromPath(path) {
    const pathname = String(path || '').trim()
    const match = pathname.match(/^\/(ru|en)(?=\/|$)/i)
    return normalizeLocale(match?.[1] || '')
}

function getStoredLocale() {
    if (typeof window === 'undefined') {
        return null
    }

    try {
        return normalizeLocale(window.localStorage.getItem(LOCALE_STORAGE_KEY))
    } catch (error) {
        return null
    }
}

export function getPreferredLocale() {
    return normalizeLocale(locale.value) || getStoredLocale() || DEFAULT_LOCALE
}

export function setLocale(nextLocale, options = {}) {
    const normalized = normalizeLocale(nextLocale) || DEFAULT_LOCALE
    const persist = options.persist !== false
    locale.value = normalized

    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('lang', normalized)
    }

    if (persist && typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(LOCALE_STORAGE_KEY, normalized)
        } catch (error) {
            // Ignore storage write errors.
        }
    }

    if (runtimeTranslationStarted) {
        scheduleRuntimeTranslation()
    }

    return normalized
}

export function t(key, params = {}) {
    const message =
        resolveMessageByKey(messages[locale.value], key)
        ?? resolveMessageByKey(messages[DEFAULT_LOCALE], key)

    if (typeof message === 'string') {
        return interpolate(message, params)
    }

    return key
}

const initialLocale = getLocaleFromPath(
    typeof window !== 'undefined' ? window.location.pathname : ''
) || getStoredLocale() || DEFAULT_LOCALE

setLocale(initialLocale, {persist: false})

const i18nContext = {
    locale,
    t,
    setLocale,
    normalizeLocale,
    getPreferredLocale,
    supportedLocales: SUPPORTED_LOCALES,
    defaultLocale: DEFAULT_LOCALE,
}

export function useI18n() {
    return inject('i18n-context', i18nContext)
}

export default {
    install(app) {
        app.provide('i18n-context', i18nContext)
        app.config.globalProperties.$locale = locale
        app.config.globalProperties.$setLocale = setLocale
        app.config.globalProperties.$t = t
        app.config.globalProperties.$translateRuntimeText = translateRuntimeText
        startRuntimeTranslation()
    },
}
