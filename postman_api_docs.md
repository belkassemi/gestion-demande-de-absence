# API Documentation pour Postman (PRD v2.0)

Ce fichier regroupe tous les endpoints, méthodes, headers, requêtes (requests) et réponses (responses) pour faciliter vos tests dans Postman.

---

## 1. Authentification
Tous les endpoints ci-dessous nécessitent d'abord de récupérer un token.

### Login
* **URL** : `POST /api/login`
* **Headers** : `Accept: application/json`
* **Request JSON** :
```json
{
  "email": "employe@test.ma", 
  "password": "password123"
}
```
* **Response 200** :
```json
{
  "message": "Login successful",
  "user": { "id": 4, "role": "employee", "name": "..." },
  "token": "1|ZzXy..."
}
```

> **IMPORTANT :** Copiez la valeur de `"token"` et allez dans *Authorization > Bearer Token* dans Postman pour l'utiliser sur toutes les requêtes qui suivent.

---

## 2. Employé : Demandes d'Absence

### Créer une nouvelle demande
* **URL** : `POST /api/absence-requests`
* **Headers** : `Authorization: Bearer {token}`, `Accept: application/json`
* **Request JSON** :
```json
{
  "user_id": 4,
  "absence_type_id": 1,
  "start_date": "2026-04-01",
  "end_date": "2026-04-05",
  "reason": "Congé annuel"
}
```
* **Response 201** :
```json
{
  "id": 1,
  "status": "pending",
  "current_level": 1,
  "days_count": 5
}
```

### Lister ses propres demandes
* **URL** : `GET /api/absence-requests`
* **Headers** : `Authorization: Bearer {token}`, `Accept: application/json`
* **Response 200** :
```json
{
  "current_page": 1,
  "data": [
    { "id": 1, "status": "pending", "days_count": 5 }
  ],
  "total": 1
}
```

### Statistiques de l'employé
* **URL** : `GET /api/absence-requests/my-stats`
* **Headers** : `Authorization: Bearer {token}`
* **Response 200** :
```json
{
  "year": 2026,
  "total_days": 15,
  "pending": 1,
  "approved": 2,
  "rejected": 0
}
```

---

## 3. Chef de Service (Niveau 1)

### Lister les demandes en attente d'approbation (Niveau 1)
* **URL** : `GET /api/chef-service/pending-requests`
* **Headers** : `Authorization: Bearer {chef_token}`
* **Response 200** :
```json
[
  {
    "id": 1,
    "user": { "name": "Employé 1" },
    "start_date": "2026-04-01",
    "status": "pending",
    "current_level": 1
  }
]
```

### Approuver ou Rejeter une demande (Niveau 1)
* **URL** : `POST /api/chef-service/requests/{id}/review` (Remplacer {id} par l'ID de la demande)
* **Headers** : `Authorization: Bearer {chef_token}`, `Accept: application/json`
* **Request JSON (Approuver)** :
```json
{
  "action": "approve",
  "comment": "OK pour moi"
}
```
* **Request JSON (Rejeter)** :
```json
{
  "action": "reject",
  "comment": "Pas assez de jours restants."
}
```
* **Response 200** :
```json
{
  "message": "Demande approuvée au niveau 1.",
  "request": { "id": 1, "status": "pending", "current_level": 2 }
}
```

---

## 4. Directeur (Niveau 2 Final)

### Lister les demandes en attente de décision finale (Niveau 2)
* **URL** : `GET /api/directeur/pending-requests`
* **Headers** : `Authorization: Bearer {directeur_token}`
* **Response 200** :
```json
[
  {
    "id": 1,
    "user": { "name": "Employé 1" },
    "status": "pending",
    "current_level": 2
  }
]
```

### Approuver ou Rejeter (Niveau 2)
* **URL** : `POST /api/directeur/requests/{id}/review`
* **Headers** : `Authorization: Bearer {directeur_token}`
* **Request JSON** :
```json
{
  "action": "approve",
  "comment": "Accord Final"
}
```
* **Response 200** :
```json
{
  "message": "Demande approuvée au niveau 2.",
  "request": { "id": 1, "status": "approved", "current_level": 3 }
}
```

### Exporter le Rapport (Directeur)
* **URL** : `GET /api/directeur/reports/export`
* **Response** : Téléchargement du fichier CSV brut.

---

## 5. Administrateur (Lecture / Gestion des accès)

L'admin ne peut pas approuver les demandes, mais il a accès à toutes les données.
Assurez-vous de vous connecter avec le compte admin pour obtenir son token.

### Dashboard Global
* **URL** : `GET /api/admin/dashboard`
* **Headers** : `Authorization: Bearer {admin_token}`
* **Response 200** :
```json
{
  "total_requests": 150,
  "pending": 5,
  "approved": 140,
  "rejected": 5,
  "total_users": 50
}
```

### Statistiques Globales
* **URL** : `GET /api/admin/statistics`
* **Response 200** : (Données par département et par mois)

### Gérer les Utilisateurs (CRUD)
* **Lire tous les users** : `GET /api/admin/users`
* **Créer un user** : `POST /api/admin/users`
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "role": "employee",
  "department_id": 1,
  "service_id": 2,
  "chef_service_id": 3,
  "is_active": true
}
```
* **Modifier un user** : `PUT /api/admin/users/{id}`
* **Supprimer un user** : `DELETE /api/admin/users/{id}`

### Gérer les Services (CRUD)
* **Lire tous** : `GET /api/admin/services`
* **Créer** : `POST /api/admin/services`
```json
{
  "name": "Equipe Web",
  "department_id": 1,
  "chef_service_id": 2
}
```
* **Modifier** : `PUT /api/admin/services/{id}`
* **Supprimer** : `DELETE /api/admin/services/{id}`
