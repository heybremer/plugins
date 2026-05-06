# Code-review graph snapshot

Statistics from **code-review-graph** for this repo (`plugins/`). Updated when you run `code-review-graph build` or incremental `update` / watcher.

## Current snapshot

| Metric | Value |
|--------|------:|
| Files in graph | 82 |
| Total nodes | 784 |
| Total edges | 3,907 |
| Languages | PHP, JavaScript |
| Communities | 16 |

### Nodes by kind

| Kind | Count |
|------|------:|
| Function | 647 |
| File | 82 |
| Class | 55 |

### Edges by kind

| Kind | Count |
|------|------:|
| CALLS | 2,974 |
| CONTAINS | 730 |
| IMPORTS_FROM | 203 |

### Embeddings

Embeddings: **0** nodes (semantic embedding features unused until configured).

## How to refresh

```bash
cd C:\xampp\htdocs\WomosiWeb\plugins
code-review-graph build
```

Then update this file’s **Current snapshot** table if you want the doc to stay in sync (or rely on MCP `list_graph_stats` live).

## Using the graph

Prefer MCP tools over repo-wide search: architecture (`get_architecture_overview`), communities (`list_communities`), impact (`get_impact_radius`, `get_affected_flows`), and symbol lookup (`semantic_search_nodes`). See **AGENTS.md**.
