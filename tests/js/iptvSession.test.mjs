import test from 'node:test'
import assert from 'node:assert/strict'

import {
    buildPersistedIptvState,
    isPersistedIptvStateOwnedBy,
    IPTV_RECENT_LIMIT,
    parsePersistedIptvState,
} from '../../resources/js/utils/iptvSession.mjs'

test('buildPersistedIptvState preserves playlist context and current channel', () => {
    const payload = buildPersistedIptvState({
        ownerScope: 'user:42',
        viewMode: 'favorites',
        playlistUrl: 'https://iptv.example.com/playlist.m3u8',
        currentPlaylistUrl: 'https://iptv.example.com/playlist.m3u8',
        sourceLabel: 'Main playlist',
        searchQuery: 'news',
        currentChannelId: 'channel-2',
        selectedGroup: 'Новости',
        sortMode: 'name',
        secureOnly: true,
        preferHttpsUpgrade: true,
        fitMode: 'cover',
        bufferingMode: 'balanced',
        autoStability: false,
        compatModeEnabled: true,
        compatProfile: 'fast',
        autoCompatOnCodecError: true,
        keyboardEnabled: false,
        volumeLevel: 0.55,
        muted: true,
        activeSeedId: 'builtin-7',
        favoriteChannelIds: ['channel-2', 'channel-5'],
        recentChannelIds: ['channel-2', 'channel-1'],
        channels: [
            {
                name: 'Channel 1',
                url: 'https://cdn.example.com/stream-1.m3u8',
                group: 'Новости',
                logo: 'https://cdn.example.com/logo-1.png',
            },
            {
                name: 'Channel 2',
                url: 'https://cdn.example.com/stream-2.m3u8',
                group: 'Кино',
                logo: 'https://cdn.example.com/logo-2.png',
            },
        ],
    })

    assert.equal(payload.ownerScope, 'user:42')
    assert.equal(payload.viewMode, 'favorites')
    assert.equal(payload.playlistUrl, 'https://iptv.example.com/playlist.m3u8')
    assert.equal(payload.currentPlaylistUrl, 'https://iptv.example.com/playlist.m3u8')
    assert.equal(payload.sourceLabel, 'Main playlist')
    assert.equal(payload.searchQuery, 'news')
    assert.equal(payload.currentChannelId, 'channel-2')
    assert.equal(payload.selectedGroup, 'Новости')
    assert.equal(payload.channelsSnapshot.length, 2)
    assert.equal(payload.channelsSnapshot[1].url, 'https://cdn.example.com/stream-2.m3u8')
})

test('buildPersistedIptvState normalizes invalid values and trims collections', () => {
    const payload = buildPersistedIptvState({
        viewMode: 'broken',
        selectedGroup: '',
        sortMode: 'broken',
        fitMode: 'broken',
        bufferingMode: 'broken',
        compatProfile: 'broken',
        autoStability: 'broken',
        muted: 'broken',
        keyboardEnabled: 'broken',
        volumeLevel: 99,
        favoriteChannelIds: Array.from({length: 700}, (_, index) => `favorite-${index + 1}`),
        recentChannelIds: Array.from({length: 80}, (_, index) => `recent-${index + 1}`),
        channels: [
            {
                name: 'Valid channel',
                url: 'https://cdn.example.com/stream.m3u8',
                group: 'Новости',
                logo: 'https://cdn.example.com/logo.png',
            },
            {
                name: 'Invalid channel',
                url: 'javascript:alert(1)',
                group: 'Bad',
                logo: '',
            },
        ],
    })

    assert.equal(payload.viewMode, 'all')
    assert.equal(payload.selectedGroup, 'all')
    assert.equal(payload.sortMode, 'group')
    assert.equal(payload.fitMode, 'contain')
    assert.equal(payload.bufferingMode, 'stable')
    assert.equal(payload.compatProfile, 'stable')
    assert.equal(payload.autoStability, true)
    assert.equal(payload.keyboardEnabled, true)
    assert.equal(payload.muted, false)
    assert.equal(payload.volumeLevel, 1)
    assert.equal(payload.favoriteChannelIds.length, 500)
    assert.equal(payload.recentChannelIds.length, IPTV_RECENT_LIMIT)
    assert.deepEqual(payload.channelsSnapshot, [
        {
            name: 'Valid channel',
            url: 'https://cdn.example.com/stream.m3u8',
            group: 'Новости',
            logo: 'https://cdn.example.com/logo.png',
        },
    ])
})

test('parsePersistedIptvState rejects broken payloads and sanitizes valid JSON', () => {
    assert.equal(parsePersistedIptvState('{broken json}'), null)

    const payload = parsePersistedIptvState(JSON.stringify({
        ownerScope: ' user:99 ',
        viewMode: 'recent',
        currentChannelId: 'channel-9',
        channelsSnapshot: [
            {
                name: 'Local channel',
                url: 'https://cdn.example.com/local.m3u8',
                group: 'Manual',
                logo: 'https://cdn.example.com/local.png',
            },
        ],
    }))

    assert.equal(payload.ownerScope, 'user:99')
    assert.equal(payload.viewMode, 'recent')
    assert.equal(payload.currentChannelId, 'channel-9')
    assert.equal(payload.channelsSnapshot.length, 1)
    assert.equal(payload.channelsSnapshot[0].name, 'Local channel')
})

test('buildPersistedIptvState trims snapshot when payload becomes too large', () => {
    const oversizedChannels = Array.from({length: 250}, (_, index) => ({
        name: `Channel ${index + 1}`,
        url: `https://cdn.example.com/stream-${index + 1}.m3u8`,
        group: 'Oversized',
        logo: `https://cdn.example.com/${'x'.repeat(3000)}-${index + 1}.png`,
    }))

    const payload = buildPersistedIptvState({
        channels: oversizedChannels,
    })

    assert.ok(payload.channelsSnapshot.length < oversizedChannels.length)
    assert.ok(payload.channelsSnapshot.length > 0)
})

test('isPersistedIptvStateOwnedBy matches only the same user scope', () => {
    const payload = buildPersistedIptvState({
        ownerScope: 'user:77',
        currentChannelId: 'channel-4',
    })

    assert.equal(isPersistedIptvStateOwnedBy(payload, 'user:77'), true)
    assert.equal(isPersistedIptvStateOwnedBy(payload, ' user:77 '), true)
    assert.equal(isPersistedIptvStateOwnedBy(payload, 'user:78'), false)
    assert.equal(isPersistedIptvStateOwnedBy(payload, ''), false)
})
