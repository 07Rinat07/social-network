import test from 'node:test'
import assert from 'node:assert/strict'

import {
    formatPlaybackTime,
    isMobileViewport,
    normalizePastTimestamp,
    resolvePersistedPlaybackSessionStartedAt,
    resolveSiteSessionStartedAt,
    resolveStationSessionStateFromSnapshot,
    SITE_SESSION_STARTED_AT_STORAGE_KEY,
} from '../../resources/js/utils/radioSession.mjs'

class MemoryStorage {
    constructor(initial = {}) {
        this.items = {...initial}
        this.setCalls = []
    }

    getItem(key) {
        if (!Object.prototype.hasOwnProperty.call(this.items, key)) {
            return null
        }

        return this.items[key]
    }

    setItem(key, value) {
        this.setCalls.push([key, value])
        this.items[key] = String(value)
    }
}

test('formatPlaybackTime formats invalid and short duration values', () => {
    assert.equal(formatPlaybackTime(-1), '00:00')
    assert.equal(formatPlaybackTime('abc'), '00:00')
    assert.equal(formatPlaybackTime(0), '00:00')
    assert.equal(formatPlaybackTime(61.9), '01:01')
})

test('formatPlaybackTime formats hour-based duration values', () => {
    assert.equal(formatPlaybackTime(3723), '01:02:03')
})

test('normalizePastTimestamp keeps only valid timestamps from the past', () => {
    assert.equal(normalizePastTimestamp('1500', {now: 2000}), 1500)
    assert.equal(normalizePastTimestamp('0', {now: 2000}), 0)
    assert.equal(normalizePastTimestamp('3000', {now: 2000}), 0)
    assert.equal(normalizePastTimestamp('text', {now: 2000}), 0)
})

test('resolveSiteSessionStartedAt returns stored session value when valid', () => {
    const storage = new MemoryStorage({
        [SITE_SESSION_STARTED_AT_STORAGE_KEY]: '1200',
    })

    const resolved = resolveSiteSessionStartedAt({
        storage,
        now: 2000,
    })

    assert.equal(resolved, 1200)
    assert.equal(storage.setCalls.length, 0)
})

test('resolveSiteSessionStartedAt writes now when storage value is invalid', () => {
    const storage = new MemoryStorage({
        [SITE_SESSION_STARTED_AT_STORAGE_KEY]: '9999',
    })

    const resolved = resolveSiteSessionStartedAt({
        storage,
        now: 2000,
    })

    assert.equal(resolved, 2000)
    assert.deepEqual(storage.setCalls, [[SITE_SESSION_STARTED_AT_STORAGE_KEY, '2000']])
})

test('resolvePersistedPlaybackSessionStartedAt preserves session only with station context', () => {
    assert.equal(resolvePersistedPlaybackSessionStartedAt('1500', {
        now: 2000,
        hasCurrentStation: true,
    }), 1500)

    assert.equal(resolvePersistedPlaybackSessionStartedAt('1500', {
        now: 2000,
        hasCurrentStation: false,
    }), 0)
})

test('resolveStationSessionStateFromSnapshot uses sessionStartedAt when it is valid and playing', () => {
    const state = resolveStationSessionStateFromSnapshot({
        hasCurrentStation: true,
        isPlaying: true,
        currentTime: 5,
        sessionStartedAt: 1500,
        now: 2000,
    })

    assert.deepEqual(state, {
        accumulatedMs: 0,
        startedAt: 1500,
    })
})

test('resolveStationSessionStateFromSnapshot accumulates elapsed time for paused state', () => {
    const state = resolveStationSessionStateFromSnapshot({
        hasCurrentStation: true,
        isPlaying: false,
        sessionStartedAt: 1500,
        now: 2000,
    })

    assert.deepEqual(state, {
        accumulatedMs: 500,
        startedAt: 0,
    })
})

test('resolveStationSessionStateFromSnapshot falls back to player currentTime when needed', () => {
    const state = resolveStationSessionStateFromSnapshot({
        hasCurrentStation: true,
        isPlaying: true,
        currentTime: 42.4,
        sessionStartedAt: 0,
        now: 2000,
    })

    assert.deepEqual(state, {
        accumulatedMs: 42400,
        startedAt: 2000,
    })
})

test('resolveStationSessionStateFromSnapshot starts from now when playing with empty snapshot', () => {
    const state = resolveStationSessionStateFromSnapshot({
        hasCurrentStation: true,
        isPlaying: true,
        currentTime: 0,
        sessionStartedAt: 0,
        now: 2000,
    })

    assert.deepEqual(state, {
        accumulatedMs: 0,
        startedAt: 2000,
    })
})

test('resolveStationSessionStateFromSnapshot resets when there is no current station', () => {
    const state = resolveStationSessionStateFromSnapshot({
        hasCurrentStation: false,
        isPlaying: true,
        currentTime: 88,
        sessionStartedAt: 1500,
        now: 2000,
    })

    assert.deepEqual(state, {
        accumulatedMs: 0,
        startedAt: 0,
    })
})

test('isMobileViewport recognizes mobile width correctly', () => {
    assert.equal(isMobileViewport(760), true)
    assert.equal(isMobileViewport(761), false)
    assert.equal(isMobileViewport('bad'), false)
})
