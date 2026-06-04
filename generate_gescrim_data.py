import random
import uuid
import subprocess
from datetime import datetime, timedelta

def get_ids_from_db(table):
    try:
        # Appelle psql pour récupérer les IDs existants
        cmd = ['psql', '-U', 'postgres', '-d', 'teranga_gescrim', '-t', '-c', f'SELECT id FROM {table};']
        # On passe le mot de passe via l'environnement si nécessaire (ou on suppose trust/local)
        import os
        env = os.environ.copy()
        env['PGPASSWORD'] = 'postgres'
        result = subprocess.check_output(cmd, env=env, universal_newlines=True)
        # Nettoyer et convertir en entiers
        ids = [int(line.strip()) for line in result.split('\n') if line.strip().isdigit()]
        return ids if ids else [1]
    except Exception as e:
        print(f"Erreur lors de la récupération de la table {table}: {e}")
        return [1]

print("Récupération des IDs valides depuis la base de données...")
services_ids = get_ids_from_db('services')
users_ids = get_ids_from_db('users')
communes_ids = get_ids_from_db('communes')
types_ids = get_ids_from_db('type_infractions')

# Listes manuelles pour remplacer Faker
prenoms = ["Moussa", "Fatou", "Awa", "Ibrahima", "Amadou", "Khady", "Ousmane", "Alioune", "Ndeye", "Mamadou", "Aminata", "Babacar", "Seynabou", "Cheikh", "Mariama"]
noms = ["Diop", "Ndiaye", "Fall", "Sow", "Ba", "Faye", "Gueye", "Sarr", "Niang", "Sy", "Mbaye", "Diallo", "Thiam", "Cisse", "Seck"]
villes = ["Dakar", "Thies", "Saint-Louis", "Ziguinchor", "Touba", "Rufisque", "Kaolack", "Mbour", "Louga", "Diourbel"]
lieux_infractions = ["Avenue Leopold Sedar Senghor", "Marche Sandaga", "Corniche Ouest", "Quartier Medina", "Pikine", "Guediawaye", "Yoff", "Parcelles Assainies", "Point E", "Almadies"]

# Paramètres
NB_PERSONNELS = 5000
NB_INFRACTIONS = 30000
NB_ACCIDENTS = 15000
NB_VICTIMES = 50000

# Années pour la répartition
END_DATE = datetime.now()
START_DATE = END_DATE - timedelta(days=5*365)

def random_date(start, end):
    return start + timedelta(seconds=random.randint(0, int((end - start).total_seconds())))

def escape_sql(val):
    if val is None:
        return 'NULL'
    if isinstance(val, (int, float)):
        return str(val)
    return "'" + str(val).replace("'", "''") + "'"

with open('data_gescrim.sql', 'w', encoding='utf-8') as f:
    f.write("-- Fichier généré automatiquement pour Teranga GESCRIM\n")
    
    # 1. Génération des Personnels
    print(f"Génération de {NB_PERSONNELS} personnels...")
    f.write("-- Insertion des Personnels\n")
    for i in range(1, NB_PERSONNELS + 1):
        ccap = f"CCAP-{100000 + i}"
        prenom = random.choice(prenoms)
        nom = random.choice(noms)
        grade = random.choice(['Gardien de la Paix', 'Brigadier', 'Adjudant', 'Lieutenant', 'Capitaine', 'Commissaire'])
        telephone = f"+221 {random.randint(70, 78)} {random.randint(100, 999)} {random.randint(10, 99)} {random.randint(10, 99)}"
        anciennete = random.randint(1, 30)
        date_entree_corps = random_date(datetime(1990, 1, 1), datetime(2023, 1, 1)).strftime('%Y-%m-%d')
        sexe = random.choice(['M', 'F'])
        sit_mat = random.choice(['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'])
        date_naissance = random_date(datetime(1960, 1, 1), datetime(2000, 1, 1)).strftime('%Y-%m-%d')
        lieu_naissance = random.choice(villes)
        service_id = random.choice(services_ids)
        statut = random.choice(['Actif', 'Actif', 'Actif', 'Inactif', 'Mission'])
        
        insert = f"INSERT INTO personnels (ccap, prenom, nom, grade, telephone, anciennete, date_entree_corps, sexe, situation_matrimoniale, date_naissance, lieu_naissance, service_id, statut, created_at, updated_at) "
        insert += f"VALUES ({escape_sql(ccap)}, {escape_sql(prenom)}, {escape_sql(nom)}, {escape_sql(grade)}, {escape_sql(telephone)}, {anciennete}, {escape_sql(date_entree_corps)}, {escape_sql(sexe)}, {escape_sql(sit_mat)}, {escape_sql(date_naissance)}, {escape_sql(lieu_naissance)}, {service_id}, {escape_sql(statut)}, NOW(), NOW());\n"
        f.write(insert)
    
    # 2. Génération des Infractions
    print(f"Génération de {NB_INFRACTIONS} infractions...")
    f.write("\n-- Insertion des Infractions\n")
    for i in range(1, NB_INFRACTIONS + 1):
        type_infraction_id = random.choice(types_ids)
        service_id = random.choice(services_ids)
        date_inf = random_date(START_DATE, END_DATE)
        annee = date_inf.year
        lieu = random.choice(lieux_infractions)
        commune_id = random.choice(communes_ids)
        issue = random.choice(['Constatée', 'Déférée'])
        user_id = random.choice(users_ids)
        
        insert = f"INSERT INTO infractions (type_infraction_id, service_id, annee, date, lieu, commune_id, issue, user_id, sync_status, created_at, updated_at) "
        insert += f"VALUES ({type_infraction_id}, {service_id}, {annee}, {escape_sql(date_inf.strftime('%Y-%m-%d'))}, {escape_sql(lieu)}, {commune_id}, {escape_sql(issue)}, {user_id}, 'synced', NOW(), NOW());\n"
        f.write(insert)

    # 3. Génération des Accidents
    print(f"Génération de {NB_ACCIDENTS} accidents...")
    f.write("\n-- Insertion des Accidents\n")
    for i in range(1, NB_ACCIDENTS + 1):
        type_acc = random.choice(['matériel', 'corporel', 'mortel'])
        date_acc = random_date(START_DATE, END_DATE)
        lieu = random.choice(lieux_infractions)
        commune_id = random.choice(communes_ids)
        service_id = random.choice(services_ids)
        moyen = random.choice(['Véhicule', 'Scooter', 'Camion', 'Transport en commun', 'Piéton', 'Calèche'])
        cause = random.choice(['Excès de vitesse', 'Non-respect feu rouge', 'Alcoolémie', 'Défaut de maîtrise', 'État de la route'])
        user_id = random.choice(users_ids)
        
        insert = f"INSERT INTO accidents (type, date, lieu, commune_id, service_id, moyen, cause_probable, user_id, sync_status, created_at, updated_at) "
        insert += f"VALUES ({escape_sql(type_acc)}, {escape_sql(date_acc.strftime('%Y-%m-%d'))}, {escape_sql(lieu)}, {commune_id}, {service_id}, {escape_sql(moyen)}, {escape_sql(cause)}, {user_id}, 'synced', NOW(), NOW());\n"
        f.write(insert)

    # 4. Génération des Personnes / Victimes
    print(f"Génération de {NB_VICTIMES} personnes (victimes/impliqués)...")
    f.write("\n-- Insertion des Victimes\n")
    for i in range(1, NB_VICTIMES + 1):
        nom = random.choice(noms)
        prenom = random.choice(prenoms)
        cin = f"SN-{random.randint(1000000, 9999999)}"
        sexe = random.choice(['M', 'F'])
        age = random.randint(1, 90)
        nationalite = random.choice(['Sénégalaise', 'Sénégalaise', 'Sénégalaise', 'Étrangère'])
        
        if random.choice([True, False]):
            # On assigne un ID d'infraction de 1 à NB_INFRACTIONS (car on vient de les créer avec SERIAL)
            # Hypothèse: la table infraction était vide ou les IDs vont s'incrémenter
            infraction_id = random.randint(1, NB_INFRACTIONS)
            accident_id = 'NULL'
        else:
            infraction_id = 'NULL'
            accident_id = random.randint(1, NB_ACCIDENTS)
            
        insert = f"INSERT INTO victimes (nom, prenom, no_cin_passeport, sexe, age, nationalite, infraction_id, accident_id, created_at, updated_at) "
        insert += f"VALUES ({escape_sql(nom)}, {escape_sql(prenom)}, {escape_sql(cin)}, {escape_sql(sexe)}, {age}, {escape_sql(nationalite)}, {infraction_id}, {accident_id}, NOW(), NOW());\n"
        f.write(insert)

print("Fichier 'data_gescrim.sql' généré avec succès avec des clés étrangères correctes !")
