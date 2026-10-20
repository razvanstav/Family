# Aplicații mobile

Stare: webul are layout adaptat mobilului. Nu există aplicație nativă, service worker sau sincronizare offline. Etapa M12 este separată de beta web; nu există o promisiune de lansare iOS/Android înaintea validării pe dispozitive.

## Pași

- [ ] **MB1 — Validare pe dispozitive.** Stabilește scopul, apoi probează camera, selectarea fișierelor, autentificarea și deep links pe Android și pe un iPhone real. Dacă livrarea nativă devine prioritară, mută acest spike înaintea asumării termenelor, actualizând planul și STATE.
- [ ] **MB2 — Adaptoare.** Implementează porturile de platformă pentru fișiere, cameră, partajare, linkuri, notificări și sesiune. Domeniile React nu importă direct Capacitor. Tokenurile native folosesc stocarea sigură a sistemului; tratează permisiuni refuzate, funcții indisponibile și anularea de către utilizator.
- [ ] **MB3 — Verificare și distribuție.** Verifică pornire rece/caldă, expirarea sesiunii, logout, revocare, reluare upload, safe areas și tastatură. Golește cacheul privat și notificările relevante la logout/revocare. Pregătește separat conturile, configurările și acceptarea distribuției.

## Acceptare

- Un deep link nu ocolește autentificarea sau politica resursei, inclusiv la pornire rece.
- Refuzul unei permisiuni are cale de recuperare; fișierele temporare se eliberează și nu sunt copiate inutil integral în base64.
- Sesiunea nativă folosește Keychain/Keystore prin adaptor adecvat, fără tokenuri în localStorage sau Preferences.
- Rezultatele includ dispozitivul și sistemul testat; emularea viewportului în browser nu se raportează drept test nativ.

## Surse și limite

Consultă `Memoria_Familiei_Review_v1.2/platform-ports.ts` și secțiunea React PWA și Capacitor numai când începe acest modul. Aceste interfețe nu sunt implementate în aplicația actuală.

PWA, service worker și sincronizarea offline sunt decizii separate; nu activa stocarea offline a datelor private ca efect secundar al instalabilității. Livrarea web stabilă rămâne prima țintă.
