# Bucătărie

Stare: planificat, fără implementare de produs. Etapa M05 începe după P1–P4 și CI de bază.

## Scop

Rețete și versiuni, preparări dintr-o versiune sau preparări libere, participanți, ratinguri 1–5 și istoric. Modulul deține aceste informații; Amintiri consumă evenimentele pentru timeline.

## Pași

- [ ] **K1 — Contract și rețete.** Definește modelele, accesul, migrațiile și API-ul minim. Livrează lista, crearea și detaliul unei rețete, cu stări goale/eroare și izolare între familii.
- [ ] **K2 — Versiuni.** O editare creează o versiune nouă; versiunea folosită de o preparare rămâne neschimbată. Verifică istoricul și conflictul între două editări concurente.
- [ ] **K3 — Preparări și ratinguri.** Înregistrează preparare din versiune sau liberă, participanți și rating unic pentru actorul real. Stabilește explicit cine poate nota sau modifica o notă; nu permite impersonarea unui participant.
- [ ] **K4 — Acces, arhivare și evenimente.** Închide partajarea/revocarea, arhivarea și publicarea prin outbox. Verifică replay și evenimente duplicate; integrarea vizibilă în timeline se acceptă în AM3.

## Acceptare

- Editarea unei rețete nu schimbă istoricul unei preparări existente.
- Ratingul este în intervalul 1–5, unic în cheia de business stabilită și legat de actorul autorizat.
- O rețetă sau preparare privată nu apare altui membru prin listă, ID, căutare ori timeline.
- Evenimentul pentru timeline se salvează atomic cu sursa; retry nu dublează prepararea sau proiecția.
- Fluxul complet rețetă → versiune → preparare → rating trece prin API și UI cu verificările relevante.

## Surse și căi viitoare

Consultă numai operațiile `Kitchen_`, `Recipe_`, `CookingSession_` din `Memoria_Familiei_Review_v1.2/api.inventory.txt` și schemele/contractele aferente din pachet. Ele descriu planul, nu rute existente.

Implementarea va sta în `backend/app/Modules/Kitchen/` și `frontend/src/modules/kitchen/`. Contractele vor fi publice în `Contracts/`; nu importa internals din Amintiri.

Planul nutrițional, cumpărăturile automate, inventarul ingredientelor și recomandările clinice sunt în afara MVP.
