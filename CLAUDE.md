# AI Search Milvus — Dev Notes

Vector storage backend for the `ai_search` framework (see `../ai_search/CLAUDE.md` for the overall architecture). This module is its own git repo/project (`backdrop-contrib/ai_search_provider_milvus`), a sibling of `ai_search`, not a submodule.

See `README.md` in this directory for requirements and server configuration (host/port/API key/collection/metric) — don't duplicate that here.

## Code Map

- `ai_search_milvus.module` — registers the `milvus` Search API service backend
- `includes/AIMilvusService.inc` — `AIMilvusService extends SearchApiAbstractService implements AiSearchBackendInterface`; handles Search API index configuration and delegates vector ops to the client
- `includes/AiSearchMilvusVectorClient.php` — `AiSearchMilvusVectorClient extends AiSearchVectorClientBase`; HTTP client for Milvus/Zilliz Cloud (`query()`, `upsert()`, `stats()`)

## Known Constraints

- Works against a Milvus instance or Zilliz Cloud; credentials are Key-module references, and collection metadata is scoped by Search API index ID.
- DDEV Milvus support lives in `ai_search`'s `resources/.ddev/docker-compose.milvus.yaml`, not in this module.

**Last Updated:** 2026-07-07 by Claude
