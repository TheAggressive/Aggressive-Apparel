# Brand voice & UX writing

How The Aggressive sounds, in the theme's own strings and in content written
in the editor. **Fewer empty words, stronger ideas, clearer shopping.**

The mechanical rules are enforced: `bin/i18n/voice.test.mjs` (part of
`pnpm test:tools`) fails the build when any theme-owned string in
`languages/aggressive-apparel.pot` uses a retired phrase, names an invented
person, lists subcultures, or shouts a short message. Everything else here
is a review call.

## Who we are

**The Aggressive is a premium, independent apparel label shaped by
alternative culture, individuality, self-expression and creative freedom.**

- **A complete apparel label**, not a graphic-tee company. Copy talks about
  the clothing, the identity, the creative direction and the experience of
  wearing it, and leaves room for new categories and directions.
- **Original artwork is a strength, not the purpose.** Mention it where a
  piece or collection is genuinely about its artwork; don't make every brand
  line about drawing.
- **Alternative culture informs us; it doesn't fence us in.** Our roots run
  through several underground and independent movements. Don't list them
  (no "skate, punk, tattoo and moto"): say "alternative culture", or better,
  let the line show it without naming it. Never imply we are one scene's
  merch store.
- **Collections keep their own worlds.** A capsule may speak its own
  language: Defiance Division is motocross, so pit lanes and race numbers
  belong there, not in brand statements.

## The voice

| We are                                   | So we write                                                                   |
| ---------------------------------------- | ----------------------------------------------------------------------------- |
| Independent, unapologetic                | Plain statements. No hedging, no asking permission.                           |
| Mature, intelligent, confident           | Short sentences that assume the reader is smart.                              |
| Direct, sharp, occasionally provocative  | One idea per line. Provocation comes from an idea, not volume.                |
| Individual and self-expressive           | Write to a person choosing for themselves, never to a tribe or a type.        |
| Authentic, not performatively rebellious | No borrowed attitude, no forced edge. If a line could sell any brand, cut it. |
| Minimal when the clothes can speak       | The image carries the section; the copy names it.                             |

Two tests for any campaign line:

1. **The logo test.** Cover the logo. Could a competitor print this line
   unchanged? "Premium independent streetwear" passes the claims check but
   fails this: any label could run it. "Stand out from the crowd" fails
   worse: it belongs to everyone.
2. **The idea test.** Does the line say something, or only describe
   something? "Uniforms are usually issued. This one you choose." says
   something: it is an argument, and only the brand whose slogan is
   "Rebellion Has a Uniform" can make it.

## Finding the idea

Original lines come from material only we have. Start from one of these,
not from a feeling ("bold", "edgy", "raw"):

- **The slogan's tension.** A uniform is imposed; ours is chosen. Issue,
  rank, regulation, dress code: the uniform's own vocabulary turned around
  ("Pick your issue.", "Built for personal style, not a dress code.").
- **Independence, made concrete.** Who decides, and who doesn't: no trend
  report, no sign-off but ours ("If it carries our name, we made the
  call.", "Still drawing our own lines.").
- **The clothes themselves.** Fit, cut, weight, how a piece is worn and
  lived in, the moment it's for ("Cut for women, not shrunk for them.",
  "For the cold walk home.", "How it's worn").
- **Plain truth said flatly.** Real data stated without decoration reads as
  confidence ("Ranked by real orders, nothing else.").
- **A turn on the expected.** Set up the cliché, then refuse it ("Nothing
  borrowed. Nothing toned down.").

Keep it short: one idea per line, no adjective doing the work a noun should.
If a line needs "bold", "authentic" or "unapologetic" to land, it hasn't
found its idea yet.

## Where personality goes

| Tier              | Surfaces                                                           | Register                                                                   |
| ----------------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| **Campaign**      | Hero, editorial headlines, collection features, lookbook, about    | Full voice. Sharp, specific, occasionally provocative.                     |
| **Merchandising** | Section eyebrows, category and collection intros, cards, CTAs      | Direct and specific. Name the thing; one beat of voice at most.            |
| **Transactional** | Cart, checkout, payment, shipping, returns, account, errors, forms | Plain and familiar commerce language. No voice. Clarity is the brand here. |

Never trade clarity for voice in the transactional tier. "Proceed to
checkout", "Add to cart" and "Your cart is empty." are correct because
customers recognize them instantly.

## Claims

We say only what we can stand behind.

- **Safe to state:** the label is independent; it has been around since
  2010; it is shaped by alternative culture; its designs and creative
  direction are its own. Original artwork, where a piece really carries it.
- **Needs proof before it ships:** materials and weights, construction,
  printing or production methods, ethics or sourcing, durability, stock
  levels or scarcity ("limited", "sells out fast", "no restocks"), shipping
  speed or cutoffs, return terms, exclusivity, popularity ("best seller",
  "trending"), guarantees.
- **Data-driven claims come from data.** "Best sellers" only ranks real sales;
  "Only 3 left" comes from stock; the free-shipping amount comes from the
  shipping settings. Copy never hard-codes a number the store owns.
- **People are real or absent.** No invented founders, customers, creators,
  handles or reviews. Starter patterns use **bracketed prompts**:
  `[Founder name]`, `[@handle]`, `"[Their words, used with permission.]"`.
  Prompts are visible on purpose, so an unedited pattern is noticed rather
  than published as fact.

## The slogan

**"Rebellion Has a Uniform."** It is a signature, not a refrain: at most once
per page, in a place of weight (the homepage hero, the footer brand line, a
manifesto). Don't paraphrase it into section headings ("Find your uniform"),
and don't stack it with other attitude lines.

## Retired language

Don't use these, and don't replace them with equally generic rebellion
lines:

- Unleash your style · Stand out from the crowd · Elevate your wardrobe
- For the bold · Built different · Not for everyone · No apologies
- Fresh drops, just landed · Refuse to blend in · Wear your attitude
- Game-changer · Must-have · Level up
- "UNLOCKED!" and other gamified shipping language
- Unsupported scarcity or quality ("sells out fast", "premium quality
  guaranteed")

## Component conventions

| Component                   | Convention                                                                         | Example                                                               |
| --------------------------- | ---------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| Buttons and CTAs            | Verb + object, sentence case in source (CSS sets display case). Say where it goes. | Shop new arrivals · Shop women's · Back to home                       |
| Eyebrows                    | Two or three words that classify, not decorate.                                    | Shop by category · New in · Heavy rotation                            |
| Headlines                   | One idea. Campaign tier may provoke; merchandising tier names.                     | Nothing here is borrowed. · Pick your issue. · Accessories            |
| Confirmations               | Past tense, no exclamation mark.                                                   | Added · Added to your cart · All items added to your cart.            |
| Errors                      | What failed, then what to do. No blame, no "Oops".                                 | Couldn't add this to your cart. Try again.                            |
| Empty states                | State it plainly, then offer the next step.                                        | Your cart is empty. → Shop all products                               |
| No results                  | Say nothing matched and suggest a fix.                                             | Nothing matched that search. Try a different word.                    |
| Stock                       | Data only, calm.                                                                   | In stock · Out of stock · Only 3 left                                 |
| Shipping progress           | Amount, then the benefit. Accurate about what qualifies.                           | $12 away from free shipping · Your order qualifies for free shipping. |
| Back-in-stock               | Say what happens next.                                                             | We'll email you when it's back in stock.                              |
| Screen-reader announcements | Same words as the visible message, full sentence.                                  | Added to your cart.                                                   |

Mechanics:

- Sentence case everywhere in source; display case is a CSS decision.
- No exclamation marks in interface copy. Campaign copy may use one, rarely.
- Curly apostrophes in prose (`’`), numerals for quantities and prices.
- The brand in customer copy is **The Aggressive**. "Aggressive Apparel" is
  the theme and admin name.
- Women's / Men's take the apostrophe.

## Localization and compatibility

- Every theme string goes through `__()` / `esc_html__()` with the
  `aggressive-apparel` domain; keep `%s` / `%1$s` placeholders and
  `{em}…{em_end}` / `{amount}` tokens intact, with a translator comment.
  Never concatenate sentences from fragments.
- Changing a msgid untranslates it in every locale until the catalogs are
  refreshed (`pnpm i18n:pot`, then the locale workflow in
  [`languages/README.md`](../languages/README.md)).
- Don't override WooCommerce or plugin strings globally (no `gettext`
  filters). Merchant-editable wording lives in **Store Copy**
  (`Feature_Settings`); change the default there, not with a filter.
- Copy should keep the words customers search for: "hoodies", "t-shirts",
  "women's", "accessories". Voice goes around the keyword, not instead of it.

## Content the theme doesn't own

Text saved in the Site Editor (header announcement bar, footer, a customized
homepage), product names and descriptions, and WooCommerce's own strings are
content, not theme code. The same guide applies, but changing them is an
editorial decision made in WordPress, not in this repository.
