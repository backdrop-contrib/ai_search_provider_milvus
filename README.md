# AI Search Milvus

Milvus vector database provider for the Backdrop CMS AI Search module. Stores and queries embeddings in a Milvus or Zilliz Cloud instance to power AI-driven semantic search experiences.

## Requirements

- Backdrop CMS 1.x
- `ai_search` module
- A running Milvus instance or a [Zilliz Cloud](https://zilliz.com/) account accessible from your Backdrop site

## Installation

- Install this module using the official [Backdrop CMS instructions](https://backdropcms.org/user-guide/modules).

## Configuration

1. Enable this module.
2. Go to Admin -> Configuration -> Search and Metadata -> Search API and create or edit a server.
3. Select **Milvus** as the service backend.
4. Configure the server settings:
   - **Host** — URL or hostname of your Milvus instance. For Zilliz Cloud, use the full `https://` URL (e.g., `https://xxx.zillizcloud.com`).
   - **Port** — Port to connect on. Defaults to `443`; use `19530` for local Milvus.
   - **API Key** — API key or token for authentication (required for Zilliz Cloud).
   - **Collection** — Name of the Milvus collection to use. Auto-created if it does not exist and cannot be changed after indexing.
   - **Metric Type** — Distance metric for nearest-neighbor search: `COSINE` (default), `IP`, or `L2`.
5. Create a Search API index on that server and configure the embeddings engine.
6. Index your content to populate the Milvus collection.

## Issues

Bugs and feature requests should be reported in the [Issue Queue](https://github.com/backdrop-contrib/ai_search_provider_milvus/issues).

## Current Maintainer

[Justin Keiser](https://github.com/keiserjb)

## Credits

- Created for Backdrop CMS by [Justin Keiser](https://github.com/keiserjb).
- Developed with AI assistance.

## License

This project is GPL v2 software. See the LICENSE.txt file in this directory for complete text.
