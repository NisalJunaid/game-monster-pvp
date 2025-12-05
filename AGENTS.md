# Agent Guide

Product requirements:
- Pokemon-inspired monster tamer with PvP as the priority experience.
- 12 elemental types with an effectiveness matrix.
- Monsters have six stats (HP, ATK, DEF, SPATK, SPDEF, SPD), four move slots, and can be affected by status effects.
- Each species supports three-stage evolutions.
- Live GPS encounters are based on the player's current location.
- Admins define encounter zones (polygons/circles) on Google Maps; zones control spawn tables and modifiers.
- Server-authoritative encounter tickets that expire.
- PvP is deterministic using seeded RNG and provides replayable battle logs.

Tech requirements:
- Laravel 10 (PHP 8.1+).
- PostgreSQL with PostGIS, Redis, and Sanctum.

Folder structure:
- Domain subfolders should exist for Dex, Battle, Geo, and Pvp in `app/Domain`.
