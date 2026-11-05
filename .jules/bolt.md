# Bolt's Journal

## 2026-10-20 - Batch Tile Collision Queries & Index Utilization
**Learning:** During land claims (`BuyNow`/`ClaimTiles`), checking candidate tile collision previously performed an N-query loop with `whereRaw('user_boxes.lat + 0 = ?')` and `lockForUpdate()->exists()`. The raw string-to-number arithmetic prevented MySQL from utilizing the `user_boxes_lat_lng_num_index` composite index on `(lat_num, lng_num)`. Batching all candidate coordinates into a single locked query on `(lat_num, lng_num)` reduces database round-trips from $N$ to 1 and allows index seeks.
**Action:** When performing geographic/grid point lookups against `user_boxes`, always query the numeric `lat_num` and `lng_num` columns instead of raw expression casts on string coordinates, and batch coordinate comparisons instead of looping individual queries.
