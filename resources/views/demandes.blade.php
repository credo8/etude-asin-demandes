<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demandes d'actes administratifs</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f5f5; color: #1a1a1a; }
        header { background: #1E3A5F; color: #fff; padding: 1rem 1.5rem; border-bottom: 4px solid #008751; }
        main { max-width: 60rem; margin: 1.5rem auto; padding: 0 1rem; }
        form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: end; background: #fff; padding: 1rem; border-radius: .5rem; }
        label { display: flex; flex-direction: column; gap: .25rem; font-size: .9rem; }
        input, select, button { padding: .5rem; font-size: 1rem; }
        button { background: #008751; color: #fff; border: 0; border-radius: .25rem; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .table-conteneur { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 1rem; }
        th, td { text-align: left; padding: .6rem; border-bottom: 1px solid #ddd; }
        .badge { padding: .15rem .5rem; border-radius: .25rem; font-size: .8rem; color: #fff; white-space: nowrap; }
        .deposee { background: #8a6d00; }
        .en_cours { background: #1E3A5F; }
        .validee { background: #008751; }
        .rejetee { background: #E8112D; }
        #message { color: #E8112D; min-height: 1.5rem; }
        nav { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <header>
        <h1>Demandes d'actes administratifs</h1>
    </header>
    <main>
        <form id="recherche">
            <label>NPI de l'usager
                <input id="npi" maxlength="10" inputmode="numeric" required>
            </label>
            <label>Statut
                <select id="statut">
                    <option value="">Tous</option>
                    <option value="deposee">Déposée</option>
                    <option value="en_cours">En cours de traitement</option>
                    <option value="validee">Validée</option>
                    <option value="rejetee">Rejetée</option>
                </select>
            </label>
            <button type="submit">Rechercher</button>
        </form>

        <p id="message" role="alert"></p>

        <div class="table-conteneur">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Acte</th>
                        <th>Copies</th>
                        <th>Statut</th>
                        <th>Motif de rejet</th>
                        <th>Déposée le</th>
                    </tr>
                </thead>
                <tbody id="lignes"></tbody>
            </table>
        </div>

        <nav>
            <button id="precedent" type="button">Précédent</button>
            <span id="indicateur"></span>
            <button id="suivant" type="button">Suivant</button>
        </nav>
    </main>

    <script>
        const LIBELLES = {
            deposee: 'Déposée',
            en_cours: 'En cours de traitement',
            validee: 'Validée',
            rejetee: 'Rejetée',
        };
        const TYPES = {
            acte_naissance: 'Acte de naissance',
            casier_judiciaire: 'Casier judiciaire',
            certificat_residence: 'Certificat de résidence',
        };

        const formulaire = document.getElementById('recherche');
        const champNpi = document.getElementById('npi');
        const champStatut = document.getElementById('statut');
        const message = document.getElementById('message');
        const lignes = document.getElementById('lignes');
        const precedent = document.getElementById('precedent');
        const suivant = document.getElementById('suivant');
        const indicateur = document.getElementById('indicateur');

        let pageCourante = 1;
        let derniere = 1;

        function cellule(texte) {
            const td = document.createElement('td');
            td.textContent = texte;
            return td;
        }

        function cellulePourStatut(statut) {
            const td = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge ' + statut;
            badge.textContent = LIBELLES[statut] ?? statut;
            td.appendChild(badge);
            return td;
        }

        function majNavigation(total) {
            indicateur.textContent = total === 0
                ? 'Aucune demande'
                : 'Page ' + pageCourante + ' / ' + derniere + ' (' + total + ' demande(s))';
            precedent.disabled = pageCourante <= 1;
            suivant.disabled = pageCourante >= derniere;
        }

        function vider() {
            lignes.replaceChildren();
            pageCourante = 1;
            derniere = 1;
            majNavigation(0);
        }

        function afficher(corps) {
            lignes.replaceChildren();
            for (const demande of corps.data) {
                const ligne = document.createElement('tr');
                ligne.append(
                    cellule(demande.id),
                    cellule(TYPES[demande.type_acte] ?? demande.type_acte),
                    cellule(demande.nombre_copies),
                    cellulePourStatut(demande.statut),
                    cellule(demande.motif_rejet ?? ''),
                    cellule(new Date(demande.created_at).toLocaleString('fr-FR')),
                );
                lignes.appendChild(ligne);
            }
            pageCourante = corps.meta.current_page;
            derniere = corps.meta.last_page;
            majNavigation(corps.meta.total);
        }

        async function charger(page) {
            const parametres = new URLSearchParams({ numero_npi: champNpi.value.trim(), page: String(page) });
            if (champStatut.value !== '') {
                parametres.set('statut', champStatut.value);
            }
            message.textContent = '';
            try {
                const reponse = await fetch('/api/demandes?' + parametres.toString(), { headers: { Accept: 'application/json' } });
                const corps = await reponse.json();
                if (!reponse.ok) {
                    const details = corps.errors ? Object.values(corps.errors).flat().join(' ') : '';
                    message.textContent = details || corps.message;
                    vider();
                    return;
                }
                afficher(corps);
            } catch {
                message.textContent = 'Serveur injoignable.';
                vider();
            }
        }

        formulaire.addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            charger(1);
        });
        precedent.addEventListener('click', () => charger(pageCourante - 1));
        suivant.addEventListener('click', () => charger(pageCourante + 1));
        majNavigation(0);
    </script>
</body>
</html>
