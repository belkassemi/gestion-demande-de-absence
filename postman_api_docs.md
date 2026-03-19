# API Documentation pour Postman (PRD v2.0)

Ce document contient l'intégralité des requêtes et réponses attendues pour toutes les routes API de l'application, afin de faciliter les tests via Postman.

---

## 1. Authentification & Profil

### 1.1. Login
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
  "user": { "id": 4, "role": "employee", "name": "Mohammed Ali" },
  "token": "1|ZzXy..."
}
```

### 1.2. Logout
* **URL** : `POST /api/logout`
* **Headers** : `Authorization: Bearer {token}`, `Accept: application/json`
* **Response 200** :
```json
{ "message": "Logged out successfully" }
```

### 1.3. Voir son Profil
* **URL** : `GET /api/profile`
* **Headers** : `Authorization: Bearer {token}`, `Accept: application/json`
* **Response 200** :
```json
{
  "id": 4,
  "name": "Mohammed Ali",
  "email": "mohammed@test.ma",
  "role": "employee",
  "department": { "id": 1, "name": "IT" },
  "service": { "id": 1, "name": "Développement" },
  "chef_service": { "id": 3, "name": "Chef" }
}
```

### 1.4. Mettre à jour son Profil
* **URL** : `PUT /api/profile`
* **Headers** : `Authorization: Bearer {token}`
* **Request JSON** :
```json
{
  "name": "Mohammed Ali Updated",
  "email": "newemail@test.ma"
}
```
* **Response 200** : (User object updated)

### 1.5. Changer de Mot de Passe
* **URL** : `POST /api/profile/change-password`
* **Headers** : `Authorization: Bearer {token}`
* **Request JSON** :
```json
{
  "current_password": "password123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```
* **Response 200** :
```json
{ "message": "Password changed successfully." }
```

---

## 2. Employé : Demandes d'Absence

### 2.1. Créer une nouvelle demande
* **URL** : `POST /api/absence-requests`
* **Headers** : `Authorization: Bearer {token}`
* **Request JSON** :
```json
{
  "user_id": 4,
  "absence_type_id": 1,
  "start_date": "2026-04-10",
  "end_date": "2026-04-15",
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

### 2.2. Lister ses propres demandes
* **URL** : `GET /api/absence-requests`
* **Response 200** : Paginated list of user requests.

### 2.3. Détails d'une demande
* **URL** : `GET /api/absence-requests/{id}`
* **Response 200** : request object with approvals history and documents.

### 2.4. Modifier une demande en attente
* **URL** : `PUT /api/absence-requests/{id}`
* **Request JSON** :
```json
{
  "start_date": "2026-04-11",
  "end_date": "2026-04-16", 
  "reason": "Updated reason"
}
```
* **Response 200** : Updated request data.

### 2.5. Annuler une demande en attente
* **URL** : `DELETE /api/absence-requests/{id}`
* **Response 200** :
```json
{ "message": "Request cancelled successfully." }
```

### 2.6. Statistiques Personnelles
* **URL** : `GET /api/absence-requests/my-stats`
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

### 3.1. Lister les demandes (Niveau 1) de son équipe
* **URL** : `GET /api/chef-service/pending-requests`
* **Query Params** : (Aucun)
* **Response 200** : Liste des demandes dont `status = pending` et `current_level = 1`.

### 3.2. Détail d'une demande (avec historique de l'employé)
* **URL** : `GET /api/chef-service/requests/{id}`
* **Response 200** :
```json
{
  "id": 1,
  "employee": {
    "name": "Mohammed Ali",
    "total_absences": 12
  },
  "start_date": "2026-04-01",
  "end_date": "2026-04-05",
  "days_count": 5,
  "reason": "Vacances",
  "status": "pending"
}
```

### 3.3. Approuver ou Rejeter (Niveau 1)
* **URL** : `POST /api/chef-service/requests/{id}/review`
* **Request JSON (Approuver)** :
```json
{
  "action": "approve",
  "comment": "OK pour moi"
}
```
* **Response 200** : Demande passe au `current_level: 2`.

### 3.4. Calendrier de l'équipe
* **URL** : `GET /api/chef-service/team-calendar`
* **Query Params** : `?month=4&year=2026`
* **Response 200** : Liste des absences formattées pour un calendrier.

### 3.5. Historique total de l'équipe
* **URL** : `GET /api/chef-service/team-history`
* **Query Params** : `?status=approved&user_id=5`
* **Response 200** : Pagination de toutes les absences de l'équipe.

---

## 4. Directeur (Niveau 2)

### 4.1. Lister les demandes (Niveau 2)
* **URL** : `GET /api/directeur/pending-requests`
* **Response 200** : Liste des demandes `status = pending` et `current_level = 2`.

### 4.2. Détail d'une demande avec approbations
* **URL** : `GET /api/directeur/requests/{id}`
* **Response 200** :  Détails + `approvals` array listant le vote du chef_service.

### 4.3. Approuver ou Rejeter Finalement (Niveau 2)
* **URL** : `POST /api/directeur/requests/{id}/review`
* **Request JSON** :
```json
{
  "action": "approve",
  "comment": "Accord Final Directeur"
}
```
* **Response 200** : Demande passe à `status: approved` ou `status: rejected`.

### 4.4. Dashboard Exécutif
* **URL** : `GET /api/directeur/dashboard`
* **Response 200** :
```json
{
  "pending_for_directeur": 5,
  "approved_this_month": 45,
  "approval_rate": 94
}
```

### 4.5. Statistiques Globales
* **URL** : `GET /api/directeur/statistics`
* **Query Params** : `?year=2026`
* **Response 200** : stats by department, by type, and monthly trend.

### 4.6. Export Excel / CSV
* **URL** : `GET /api/directeur/reports/export`
* **Response** : Fichier CSV.

---

## 5. Administrateur (Lecture + Configuration)

L'admin a accès à toutes les données en lecture et configure les types, départements et rôles.

### 5.1. Dashboard Admin
* **URL** : `GET /api/admin/dashboard`
* **Response 200** : KPIs globaux.

### 5.2. Statistiques Admin
* **URL** : `GET /api/admin/statistics`
* **Response 200** : Identique au directeur.

### 5.3. Toutes les demandes (Lecture seule)
* **URL** : `GET /api/admin/all-requests`
* **Query Params** : `?status=approved&department_id=2`
* **Response 200** : Liste paginée globale.

### 5.4. Statistiques Utilisateurs (Absences par employé)
* **URL** : `GET /api/admin/all-users-stats`
* **Response 200** : Liste des utilisateurs avec leur nombre total de jours.

### 5.5. Export CSV Admin
* **URL** : `GET /api/admin/reports/export`
* **Response** : Fichier CSV.

### 5.6. Audit Logs
* **URL** : `GET /api/admin/audit-logs`
* **Response 200** : Historique des actions dans le système.

### 5.7. Utilisateurs (CRUD)
* **Lire** : `GET /api/admin/users`
* **Lire 1 user** : `GET /api/admin/users/{id}`
* **Créer** : `POST /api/admin/users`
```json
{
  "name": "Jane", "email": "jane@test.ma", "password": "password123",
  "role": "employee", "department_id": 1, "service_id": 2, "is_active": true
}
```
* **Modifier** : `PUT /api/admin/users/{id}`
* **Supprimer** : `DELETE /api/admin/users/{id}`

### 5.8. Départements (CRUD)
* **Lire** : `GET /api/admin/departments`
* **Créer** : `POST /api/admin/departments`
```json
{ "name": "IT", "code": "D-01", "director_id": 2 }
```
* **Modifier** : `PUT /api/admin/departments/{id}`
* **Supprimer** : `DELETE /api/admin/departments/{id}`

### 5.9. Services (CRUD)
* **Lire** : `GET /api/admin/services`
* **Créer** : `POST /api/admin/services`
```json
{ "name": "Développement", "department_id": 1, "chef_service_id": 3 }
```
* **Modifier** : `PUT /api/admin/services/{id}`
* **Supprimer** : `DELETE /api/admin/services/{id}`

### 5.10. Types d'Absence (CRUD)
* **Lire** : `GET /api/admin/absence-types`
* **Criérer** : `POST /api/admin/absence-types`
```json
{
  "name": "Congé Maternité",
  "requires_document": true,
  "color": "#F472B6",
  "is_active": true
}
```
* **Modifier** : `PUT /api/admin/absence-types/{id}`
* **Supprimer** : `DELETE /api/admin/absence-types/{id}`
