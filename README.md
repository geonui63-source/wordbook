# 📘 KotobaAI (Wordbook)

**KotobaAI** is a Japanese–Korean vocabulary learning web application that helps learners **select, store, and review only the meanings they actually need**, instead of passively consuming crowded dictionary entries.

AI generates multiple sense candidates for a word, and users **choose and save only the relevant meanings**, maintaining a clean and personalized wordbook.

---

## 🔥 One-Line Pitch
> **An AI-powered Japanese vocabulary learning app that generates multiple senses for a word and lets users select, store, and review only what they need.**

---

## 🎯 Problem It Solves
When learning Japanese vocabulary:

- Dictionary results often contain **too many meanings**
- Organizing **examples and translations manually is time-consuming**
- Learning records are scattered across **notes, screenshots, and memo apps**
- This breaks the **review and retention flow**

✅ **KotobaAI solves this by centralizing the workflow:**  
**Sense generation → selection → storage → review → analytics**

---

## ⭐ Key Features (Current Implementation)

### 1️⃣ AI-Based Word Addition (Polysemy Handling)
- Input a Korean or Japanese word
- AI generates **1–3 sense candidates**, each including:
  - Sense label
  - Japanese word
  - Korean meaning
  - Furigana (reading)
  - Japanese example sentence
  - Korean translation
- Users **select and save only the desired senses** into the database

---

### 2️⃣ Wordbook Management (List / Search / Edit / Delete)
- View saved words in a **card-based UI**
- Search by:
  - Word
  - Meaning
  - Example sentence
  - Sense label
- Edit entries directly with **inline editing**
- Delete unnecessary items easily

---

### 3️⃣ Daily Learning Log (Date-Based View)
- View words added on a **specific date**
- Separate tracking for:
  - Korean-initiated searches
  - Japanese-initiated searches

---

### 4️⃣ Learning Analytics (Day / Week / Month)
- Visualized statistics of:
  - Words added per period
  - Total count
  - Average learning volume
- Helps users monitor **learning consistency and trends**

---

### 5️⃣ Quiz & Random Review Mode
- **Quiz mode** to actively recall stored vocabulary
- **Random review mode** to reinforce long-term memory through repetition

---

### 6️⃣ Handwritten Kanji Recognition Workflow
- Draw kanji directly on a canvas
- AI recognizes handwritten Japanese text
- Recognized text is sent directly to the **Add Word** page
- Maintains a smooth learning flow from unknown kanji → vocabulary storage

---

### 7️⃣ Light / Dark Theme Support
- Full light and dark mode support
- Unified UI theming across all pages

---

## 🧩 File Structure & Responsibilities

| File | Description |
|-----|------------|
| `index.php` | Main hub page that navigates to all learning features |
| `add.php` | Generates AI sense candidates and saves selected meanings |
| `list.php` | Search, edit, and delete saved vocabulary entries |
| `today.php` | Displays words learned on the current day |
| `quiz.php` | Quiz-based vocabulary review |
| `random.php` | Randomized vocabulary review for repetition |
| `stats.php` | Learning statistics (daily / weekly / monthly) |
| `jlpt.php` | JLPT-level based vocabulary generation and study |
| `kanji_draw.php` | Handwritten kanji recognition and workflow integration |
| `theme.php` | Global UI theme and light/dark mode handling |
| `db.php` | MySQL database connection and configuration |
| `upload_hero.php` | Upload and manage hero images for the main page |

---

## 🛠 Tech Stack
- **PHP (Vanilla)**
- **MySQL**
- **XAMPP (Local Development)**
- **OpenAI API (Text & Vision)**
- HTML / CSS / JavaScript

---

## 🔐 Security & Configuration
- Sensitive files (`db.php`, `config.php`) are excluded via `.gitignore`
- Sample configuration files are provided:
  - `db.sample.php`
  - `config.sample.php`
- This allows safe cloning and setup without exposing credentials

---

## 🎓 Purpose
This project was built as a **personal language-learning tool** and is also used as a **portfolio project** to demonstrate:

- Practical AI integration
- Real-world UX problem solving
- Full-stack PHP web development
- Secure configuration handling with GitHub

---

## 🚀 Future Improvements
- Spaced repetition algorithm (SRS)
- User authentication & multi-account support
- Cloud deployment (AWS / Railway / Render)
- Mobile-optimized UI
