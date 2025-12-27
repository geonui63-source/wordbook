# KotobaAI (Wordbook)

A lightweight Japanese vocabulary learning web app built with **PHP + MySQL**, featuring:
- AI-generated vocabulary & example sentences
- Handwritten Kanji recognition (drawing pad)
- JLPT level-based vocabulary generator
- Personal wordbook saving and browsing

---

## Features

### ✅ Add Word (AI senses)
- Enter a Korean or Japanese word
- AI returns **multiple senses** (polysemy support)
- Select senses and save them into your database

### ✍️ Kanji Draw (Handwriting recognition)
- Draw kanji/kana on a canvas
- AI recognizes the handwriting
- Sends recognized text into **Add Word** page

### 🎯 JLPT Vocabulary Generator
- Choose JLPT level (N1–N5)
- Generate 3 vocabulary cards at a time
- Save selected items to your wordbook (duplicate-safe)

---

## Tech Stack
- PHP (vanilla)
- MySQL
- XAMPP (local dev)
- OpenAI API (vision + text)

---

## Setup (Local)

1. Put this project in:
   `C:\xampp\htdocs\wordbook`

2. Create config file:
   - Copy `config.sample.php` → `config.php`
   - Fill in your API key inside `config.php`

3. Configure DB:
   - Create DB and table using your existing schema
   - Update `db.php` (local only)

4. Run:
   - Start Apache + MySQL in XAMPP
   - Open: `http://localhost/wordbook`

---

## Security Note
This repo ignores sensitive files:
- `config.php`
- `db.php`

Use sample files:
- `config.sample.php`
- `db.sample.php`

---

## Screens (optional)
Add screenshots here later:
- Add word page
- JLPT page
- Kanji draw page
