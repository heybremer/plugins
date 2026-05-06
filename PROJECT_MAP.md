# Project map (coarse)

This is a **high-level orientation** document. It is derived from the **code-review-graph** community layout (not a manual inventory of every file). For symbols and call paths, use the graph MCP (`semantic_search_nodes`, `query_graph`, `get_community`, etc.).

Repo root: **`plugins/`** — JTL-Shop style plugins; each top-level folder is one plugin.

## Plugins by graph community

Graph communities are clusters of related functions/classes. Names below are **community ids** from the graph; the **Plugin** column is the main folder to open for that cluster.

| Community (graph) | Size (nodes) | Cohesion | Primary plugin folder |
|-------------------|-------------:|---------:|------------------------|
| js-fk | 169 | 0.26 | `FilterKonfiguratorSearch/` |
| tasarim-aktion | 94 | 0.24 | `ActionProduct/` |
| tests-config | 70 | 0.42 | `SideLeftFiltre/` |
| js-brands | 68 | 0.28 | `MarkeFiltre/` |
| newfilter-state | 57 | 0.28 | `NewFilter/` |
| js-messe | 36 | 0.17 | `Messe/` |
| js-search | 34 | 0.32 | `LiveSearch/` |
| tests-hook | 29 | 0.29 | `SideLeftPanel/` |
| bloggie-bloggie | 26 | 0.19 | `Bloggie/` |
| top10list-fetch | 26 | 0.18 | `Top10List/` |
| cardproduct-fetch | 25 | 0.19 | `CardProduct/` |
| js-ensure (larger) | 20 | 0.33 | `GoogleReview/` |
| produktcard-fetch | 19 | 0.17 | `Produktcard/` |
| js-ensure (smaller) | 14 | 0.21 | `HomepageInjector/` |
| fourlayout-ensure | 8 | 0.22 | `FourLayout/` |
| bannerproduckt-ensure | 7 | 0.33 | `BannerProduckt/` |

**Note:** Two communities share the label `js-ensure`; they map to different plugins (see sizes).

## Typical entry points

- **Bootstrap / hooks:** each plugin’s `Bootstrap.php` at the plugin root.
- **Admin:** often `adminmenu/dashboard.php` (varies by plugin).
- **Front JS:** often `templates/js/*.js` under the plugin.
- **Tests:** `tests/` inside the plugin when present.

Refresh this map after large refactors by rebuilding the graph (`code-review-graph build`) and re-checking communities in MCP.
