# EasyUI — EUI1

Sursă: [Surajmaurya1/easyui](https://github.com/Surajmaurya1/easyui), commit [`7c106d4a195f9ce566ffbbc672fc48bed68d30bd`](https://github.com/Surajmaurya1/easyui/tree/7c106d4a195f9ce566ffbbc672fc48bed68d30bd), consultat la 25 octombrie 2026. Licența MIT originală este păstrată integral, fără modificări, în [easyui-LICENSE.txt](easyui-LICENSE.txt).

| Componentă Amora                 | Model EasyUI                                                      | Implementare originală la commitul consultat                                                                                                    |
| -------------------------------- | ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `src/shared/ActionButton.tsx`    | [Morphing Button](https://easyui.site/components/morphing-button) | [morphing-button.json](https://github.com/Surajmaurya1/easyui/blob/7c106d4a195f9ce566ffbbc672fc48bed68d30bd/public/source/morphing-button.json) |
| `src/shared/FocusInput.tsx`      | [LockInput](https://easyui.site/components/lock-input)            | [lock-input.json](https://github.com/Surajmaurya1/easyui/blob/7c106d4a195f9ce566ffbbc672fc48bed68d30bd/public/source/lock-input.json)           |
| `src/shared/FeedbackMessage.tsx` | [Alert](https://easyui.site/components/alert)                     | [alert.json](https://github.com/Surajmaurya1/easyui/blob/7c106d4a195f9ce566ffbbc672fc48bed68d30bd/public/source/alert.json)                     |

Adaptările păstrează comportamentele utile: starea reală de salvare fără schimbarea dimensiunii butonului, reacția discretă a conturului la focus și apariția mesajelor cu rolurile accesibile existente. Controalele native și atributele lor rămân în React, fără stare suplimentară pentru câmpuri, validare proprie, succes simulat sau dispariția automată a mesajelor.

Mișcarea este în `src/shared/feedback.css`, folosind tokenurile și regula comună pentru mișcare redusă din Amora. Fonturile, paleta, spațierea, colțurile și clasele vizuale existente sunt păstrate. Implementările nu importă Framer Motion, Tailwind, `cn`, fonturile ori tema EasyUI și nu adaugă dependențe.
