# Brand voice & UX writing

How The Aggressive sounds, in the theme's own strings and in content written
in the editor. The artwork is the personality; copy sharpens it and gets out of
the way. **Fewer empty words, stronger ideas, clearer shopping.**

The mechanical rules are enforced: `bin/i18n/voice.test.mjs` (part of
`pnpm test:tools`) fails the build when any theme-owned string in
`languages/aggressive-apparel.pot` uses a retired phrase, names an invented
person, or shouts a short message. Everything else here is a review call.

## The voice

| We are                                     | So we write                                                           |
| ------------------------------------------ | --------------------------------------------------------------------- |
| Independent, unapologetic                  | Plain statements. No hedging, no asking permission.                   |
| Mature, intelligent, confident             | Short sentences that assume the reader is smart.                      |
| Direct, sharp, occasionally provocative    | One idea per line. Provocation comes from an idea, not volume.        |
| Rooted in original artwork and the culture | Concrete nouns: artwork, graphic, drawing, skate, punk, tattoo, moto. |
| Authentic, not performatively rebellious   | No borrowed attitude. If a line could sell any brand, cut it.         |
| Minimal when the artwork can speak         | The image carries the section; the copy names it.                     |

Two tests for any campaign line:

1. **The logo test.** Cover the logo. Could a competitor print this line
   unchanged? "Original artwork on everyday clothing" passes the claims check
   but fails this: it is a description, and any graphic-tee brand could run
   it. "Stand out from the crowd" fails worse: it belongs to everyone.
2. **The idea test.** Does the line say something, or only describe
   something? "Uniforms are usually issued. This one you choose." says
   something: it is an argument, and only the brand whose slogan is
   "Rebellion Has a Uniform" can make it.

## Finding the idea

Original lines come from material only we have. Start from one of these,
not from a feeling ("bold", "edgy", "raw"):

- **The slogan's tension.** A uniform is imposed; ours is chosen. Issue,
  rank, regulation, dress code: the uniform's own vocabulary turned around
  ("Pick your issue.").
- **The drawn line.** The artwork is the product, so the language of drawing
  works twice: lines, ink, linework, the hand, sign-off ("Every line is
  ours.", "Still drawing our own lines.").
- **The culture's artifacts.** Name real objects, not attitudes: skate decks,
  tattoo flash, punk flyers, race numbers, the pit lane ("Drawn from the pit
  lane, not the showroom.").
- **Plain truth said flatly.** Real data stated without decoration reads as
  confidence ("Ranked by real orders, nothing else.").
- **A turn on the expected.** Set up the cliché, then refuse it ("Cut for
  women, not shrunk for them.", "Worn, not framed.").

Keep it short: one idea per line, no adjective doing the work a noun should.
If a line needs "bold", "authentic" or "unapologetic" to land, it hasn't
found its idea yet.

## Where personality goes

| Tier              | Surfaces                                                           | Register                                                                   |
| ----------------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| **Campaign**      | Hero, editorial headlines, artwork features, lookbook, about       | Full voice. Sharp, specific, occasionally provocative.                     |
| **Merchandising** | Section eyebrows, category and collection intros, cards, CTAs      | Direct and specific. Name the thing; one beat of voice at most.            |
| **Transactional** | Cart, checkout, payment, shipping, returns, account, errors, forms | Plain and familiar commerce language. No voice. Clarity is the brand here. |

Never trade clarity for voice in the transactional tier. "Proceed to
checkout", "Add to cart" and "Your cart is empty." are correct because
customers recognize them instantly.

## Claims

We say only what we can stand behind.

- **Safe to state:** the artwork is original to The Aggressive; the label is
  independent; it has been around since 2010; the culture it comes from
  (skate, punk, tattoo, moto).
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
| Eyebrows                    | Two or three words that classify, not decorate.                                    | Shop by category · Latest artwork · Heavy rotation                    |
| Headlines                   | One idea. Campaign tier may provoke; merchandising tier names.                     | Every line is ours. · Pick your issue. · Accessories                  |
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
