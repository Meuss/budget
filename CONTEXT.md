# Budget

A single-owner personal finance app: it classifies bank transactions to show where money goes (Budget), and tracks the owner's total wealth over time from manually entered balances (Patrimoine). The two areas share no data.

## Language

### Budget

**Catégorie**:
A classification for bank transactions (expense, income, savings or transfer). Belongs to Budget only.
_Avoid_: Classe (that is a Patrimoine term)

### Patrimoine

**Patrimoine**:
The owner's total wealth: the sum of every Avoir's balance in a Relevé. Assets only, in CHF.
_Avoid_: Net worth, fortune

**Avoir**:
One place that holds wealth and has a balance, such as a current account, a vested-benefits account or a brokerage account. Belongs to exactly one Classe and has a short title and description.
_Avoid_: Account, compte, holding, Catégorie

**Classe**:
An owner-defined, ordered group of Avoirs, such as "Liquidités" or "2e pilier", with a short title and description.
_Avoid_: Catégorie, pillar, asset class

**Relevé**:
The balance of every active Avoir on a precise date. It can be dated in the past and Relevés need not be evenly spaced.
_Avoid_: Snapshot, entry

**Versement**:
Money paid into (or, if negative, taken out of) an Avoir since the previous Relevé, recorded alongside its balance so returns can be told apart from contributions. Only Avoirs that track Versements have one.
_Avoid_: Contribution, deposit

**Versement mensuel**:
The standing-order amount an Avoir receives each month. It is the default from which a Relevé's Versement is proposed, never a recorded payment itself.
_Avoid_: Standing order, ordre permanent

**Archivé**:
The state of an Avoir that is closed from a given date: Relevés on or after that date leave it out, but its earlier balances stay in every Relevé and chart.
_Avoid_: Deleted, closed
