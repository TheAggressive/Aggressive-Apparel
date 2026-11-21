# Brand voice & UX writing

How The Aggressive sounds, in the theme's own strings and in content written
in the editor. **Fewer empty words, stronger ideas, clearer shopping.**

The mechanical rules are enforced: `bin/i18n/voice.test.mjs` (part of
`pnpm test:tools`) fails the build when any theme-owned string in
`languages/aggressive-apparel.pot` uses a retired phrase, names an invented
person, lists subcultures, or shouts a short message. Everything else here
is a review call.

## Who we are

**The Aggressive is an independent apparel label, est. 2010, named for an old
compliment: "aggressive" meant nobody softened it.** Graphic pieces, plain
essentials and accessories, without the edges sanded off to please everyone.

- **A point of view, not a philosophy.** The brand never describes its own
  attitude, independence or rebellion; what it makes, leaves out and finds
  funny shows it. It is not a motivational brand: no talk of commitment,
  purpose or living your truth.
- **A complete apparel label.** Copy is about the clothes and the life they
  are worn in. Original artwork is mentioned where a piece really carries it.
- **Roots shown, not listed.** Where we come from appears in the odd
  reference ("the riff nobody turned down"), never in a list of scenes.
- **Divisions keep their own worlds.** Defiance Division is motocross: pit
  lanes and race numbers belong there, not in brand copy. Drops may be
  numbered as issues ("Issue 07").
- **The name is explained once** ("Aggressive used to be a compliment."):
  homepage section 04 and the About page. Nowhere else.

## The voice

**Sharp, dry, unbothered.** Someone with good taste and nothing to prove: says
less than it knows, is funny without signalling the joke, can be dark or
blunt, and is never needy, angry for show or earnest about itself.

**Plain by default, plus five registers.** Most copy is plain. A page gets
two or three lines with voice, a section one at most, several sections none.

| Register | Sounds like                                                       | Example                                           |
| -------- | ----------------------------------------------------------------- | ------------------------------------------------- |
| Plain    | Familiar, exact, no voice (shopping, support, labels, specs)      | Your cart is empty. · Size down for a closer fit. |
| Deadpan  | Flat understatement; the joke is what's left out                  | Rebellion has a uniform. This season's is in.     |
| Dark     | Gallows wit with a straight face                                  | Dead Signal. Reception was never the point.       |
| Blunt    | Honest to the point of rude, at the industry or us, never you     | Nobody needs another hoodie. We made one anyway.  |
| Quiet    | Almost nothing; the picture carries it                            | Black. Again.                                     |
| Strange  | Form as the joke: catalogue numbers, captions that refuse to sell | Fig. 1: hoodie, worn as intended.                 |

A line is ours when it names something specific, is true, is short, trusts
the reader, and would sound wrong on any other label's site. Provocation has
to be true to land. Humour stays out of checkout, errors, shipping and
support, and never punches at the customer or a group of people.

**Not leaned on** (fine once in a long while, never a pattern): commitment,
conviction, decided, purpose, mission, journey, passion, authentic,
unapologetic, attitude, edge, rebel, bold, premium, elevate.

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

- **Safe to state:** the label is independent; it was established in 2010
  ("Est. 2010", "in 2010", never "since 2010", which implies an unbroken
  run nobody has confirmed); the range and divisions that exist today.
- **Needs proof before it ships:** that designs are made in-house; fit
  claims ("cut for women"); who chose a product ("staff picks"); materials
  and weights, construction,
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
- Unsupported scarcity or quality ("sells out fast", "limited runs",
  "premium quality guaranteed")
- For the Unruled · never meant to blend in · Trends die. Attitude doesn't.
- Every conviction ends up with a look · Loud or plain. Never undecided.

## Component conventions

| Component                   | Convention                                                                         | Example                                                               |
| --------------------------- | ---------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| Buttons and CTAs            | Verb + object, sentence case in source (CSS sets display case). Say where it goes. | Shop new arrivals · Shop women's · Back to home                       |
| Eyebrows                    | Two or three words that classify, not decorate.                                    | Shop by category · New in · Heavy rotation                            |
| Headlines                   | One idea. Campaign tier may provoke; merchandising tier names.                     | Aggressive used to be a compliment. · Pick your issue. · Accessories  |
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
