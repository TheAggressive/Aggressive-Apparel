/**
 * Ambient declarations for stylesheet imports.
 *
 * Blocks and editor scripts import their CSS for side effects only
 * (`import './style.css';`) so webpack picks the file up and emits it through
 * mini-css-extract-plugin. There is no JavaScript value to import.
 *
 * TypeScript 5 tolerated these silently. TypeScript 6 reports TS2882 —
 * "Cannot find module or type declarations for side-effect import" — for every
 * one of them, which is why the typescript@6 upgrade fails without this file.
 * Declaring the modules with no exports states the intent exactly: the import
 * exists for its side effect and yields nothing.
 */

declare module '*.css';
declare module '*.scss';
