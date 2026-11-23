# Assistentino

Plugin WordPress che espone a Colibri, in sola lettura e con una chiave, lo stato del sito:

- versione di WordPress e PHP;
- tema attivo e tema genitore;
- plugin installati, con versione, stato attivo e aggiornamento disponibile;
- plugin in `mu-plugins`.

Non può modificare niente.

## Indirizzo

`GET https://sito.it/wp-json/assistentino/v1/stato`, con l'intestazione `X-Assistentino-Key: <chiave>`.

Se la chiave manca o è sbagliata, risponde 404 come se l'indirizzo non esistesse. Il sito conserva solo l'impronta sha256 della chiave.

## Installazione su un sito

1. Plugin → Aggiungi nuovo → Carica plugin, con `assistentino.zip` dall'ultima release.
2. Strumenti → Assistentino → «Genera la chiave».
3. Copia la chiave, che viene mostrata una volta sola, e incollala in Colibri nella pagina del sito.

## Prima pubblicazione su GitHub

1. Crea il repository **pubblico** `assistentino` su GitHub.
2. In `assistentino.php` sostituisci `OWNER/assistentino` con `utente/assistentino` in **due** punti: l'intestazione `Update URI` e la costante `ASSISTENTINO_GITHUB_REPO`.
3. Fai il push.
4. Crea il tag `v1.0.0` e fai il push del tag. L'azione GitHub crea la release con `assistentino.zip`.

## Nuova versione

1. In `assistentino.php` alza la versione in **due** punti: `Version:` e `ASSISTENTINO_VERSION`.
2. Fai il commit e il push.
3. Crea il tag `v1.0.1` e fai il push del tag. L'azione si ferma se il tag non corrisponde alla versione.
4. Entro 12 ore i siti mostrano l'aggiornamento in Bacheca → Aggiornamenti.
5. Se nella pagina Plugin attivi «Abilita aggiornamenti automatici», il sito si aggiorna da solo.

## Sicurezza

Chi controlla il repository GitHub può far arrivare codice su tutti i siti. Per questo:

- tieni attiva l'autenticazione a due fattori su GitHub;
- non dare accesso in scrittura al repository a nessun altro.
