# Patrimoine lives in plain tables, not Statamic entries

Transaction Catégories are Statamic entries edited in the control panel, but Classes, Avoirs and Relevés are plain Eloquent tables managed from a page inside the `/budget` app. Patrimoine shares no data with the transaction side, gains nothing from the CP, and must be editable in the same screen where Relevés are entered. Reusing the Statamic collection pattern was rejected to keep the two areas fully separate.
