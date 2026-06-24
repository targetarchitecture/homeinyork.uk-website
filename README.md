# homeinyork.uk — The home of The Joblings online

## Files

| File         | Purpose                                              |
|--------------|------------------------------------------------------|


## How it works

- On first visit, `index.php` queries the **Wikimedia Commons API** across
  several searches (York Minster, The Shambles, city walls, River Ouse, etc.)
  and writes the resulting image URL list to `cache/york_images.json`.
- Subsequent visits serve instantly from that cache. It refreshes once every
  24 hours.
- If the Wikimedia API is unreachable (e.g. the very first cold load times
  out), a small curated fallback set of known images is shown instead.

## Cache

`cache/york_images.json` — auto-created by PHP. Safe to delete at any time
to force an immediate refresh on the next page load.

## Wikimedia Commons

All images are sourced from Wikimedia Commons under CC (Creative Commons)
or public-domain licences. They are served directly from Wikimedia's CDN
(`upload.wikimedia.org`) — no bandwidth cost to the hosting account.

## Image counts

Typically ~50–80 unique photographs after deduplication. The page is a pure
CSS-columns masonry grid — no JavaScript, no dependencies.
