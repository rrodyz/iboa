# 📄 Guide d'Utilisation - Gestion Documentaire

## ✅ Activé

La gestion documentaire (pièces jointes) a été activée sur les modèles suivants :
- **Invoice** (Factures clients)
- **Client** (Clients)
- **Supplier** (Fournisseurs)
- **Product** (Produits)
- **SupplierInvoice** (Factures fournisseurs)
- **ClientPayment** (Paiements clients)
- **SupplierPayment** (Paiements fournisseurs)

---

## 🎯 Comment Utiliser le Composant

### 1. **Intégrer le composant dans une vue Blade**

```blade
<!-- Dans resources/views/invoices/show.blade.php -->
<x-attachments.manager 
    :model="'Invoice'" 
    :id="$invoice->id" 
    :showTitle="true" 
/>
```

### 2. **Paramètres du composant**

- `model` (string, obligatoire) : Nom du modèle (ex: "Invoice", "Client", "Product")
- `id` (integer, obligatoire) : ID de l'instance du modèle
- `showTitle` (boolean, optionnel) : Afficher le titre et le compteur (défaut: true)

### 3. **Exemple complet**

```blade
<div class="bg-white rounded-lg shadow p-6">
    <x-attachments.manager 
        :model="'Invoice'" 
        :id="$invoice->id"
    />
</div>
```

---

## 🔧 Fonctionnalités

✅ **Upload de fichiers**
- Glissez-déposez sur la zone d'upload
- Cliquez pour sélectionner des fichiers
- Upload multiple simultané
- Barre de progression

✅ **Types de fichiers acceptés**
- Images (JPEG, PNG, GIF, WebP)
- PDF
- Excel (XLSX, XLS)
- Word (DOCX)
- Texte (TXT, CSV)
- Taille max : 10 MB

✅ **Gestion des pièces jointes**
- Affichage en liste avec aperçu (images)
- Téléchargement des fichiers
- Suppression avec confirmation
- Informations : nom, taille, date

✅ **Interface réactive**
- Gestion des erreurs
- Barre de progression
- État de chargement
- Aucune page refresh

---

## 📝 Exemples d'Intégration

### **Factures**
```blade
<!-- resources/views/invoices/show.blade.php -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <!-- Détails de la facture -->
    </div>
    <div>
        <x-attachments.manager model="Invoice" :id="$invoice->id" />
    </div>
</div>
```

### **Clients**
```blade
<!-- resources/views/clients/show.blade.php -->
<div class="space-y-6">
    <!-- Infos client -->
    <x-attachments.manager model="Client" :id="$client->id" />
</div>
```

### **Produits**
```blade
<!-- resources/views/products/show.blade.php -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <!-- Images produit -->
    </div>
    <div>
        <x-attachments.manager model="Product" :id="$product->id" />
    </div>
</div>
```

---

## 🔌 API REST

### **Récupérer les pièces jointes**
```
GET /attachments?attachable_type=Invoice&attachable_id=1
```

**Réponse (200)**
```json
[
  {
    "id": 1,
    "filename": "facture_scan.pdf",
    "path": "attachments/invoice/1/facture_scan.pdf",
    "size": "2.5 Mo",
    "mime_type": "application/pdf",
    "label": "Original signé",
    "is_image": false,
    "is_pdf": true,
    "created_at": "22/04/2026 14:30"
  }
]
```

### **Uploader une pièce jointe**
```
POST /attachments
Content-Type: multipart/form-data

- file (file, obligatoire)
- attachable_type (string, obligatoire)
- attachable_id (integer, obligatoire)
- label (string, optionnel)
```

### **Télécharger une pièce jointe**
```
GET /attachments/{id}/dl
```

### **Supprimer une pièce jointe**
```
DELETE /attachments/{id}
```

---

## 🗂️ Structure de Stockage

Les fichiers sont organisés ainsi :
```
storage/app/attachments/
├── invoice/
│   ├── 1/
│   │   ├── facture_scan.pdf
│   │   └── preuve_paiement.jpg
│   └── 2/
├── client/
│   ├── 5/
│   │   ├── kbis.pdf
│   │   └── identite.jpg
│   └── 6/
├── product/
│   ├── 10/
│   │   ├── specification.pdf
│   │   └── certification.pdf
└── supplier/
    └── 3/
        └── devis.xlsx
```

---

## 📋 Notes Techniques

### **Base de Données**
La table `attachments` contient :
- `id` : Identifiant unique
- `attachable_type` : Type du modèle (polymorphe)
- `attachable_id` : ID de l'instance
- `disk` : Disque de stockage ('local')
- `path` : Chemin du fichier
- `filename` : Nom original du fichier
- `mime_type` : Type MIME
- `size` : Taille en bytes
- `label` : Étiquette/description
- `uploaded_by` : ID de l'utilisateur qui a uploadé
- `created_at`, `updated_at` : Timestamps

### **Trait HasAttachments**
Ajoute à tout modèle :
```php
$model->attachments() // Relation MorphMany
```

### **Middleware & Permissions**
- ✅ Routes protégées par `auth` et `verified`
- Chaque utilisateur peut uploader et gérer les pièces jointes
- Les suppression est autorisée pour tous les utilisateurs authentifiés
  (à adapter selon vos besoins de permissions)

---

## 🚀 Étapes Suivantes

1. **Intégrer le composant** dans vos vues de détail (Invoices, Clients, etc.)
2. **Ajuster les permissions** si nécessaire
3. **Customiser le style** du composant selon votre design
4. **Ajouter des validations** métier si besoin
5. **Documenter** l'usage pour les utilisateurs

---

## ❓ FAQ

**Q: Où sont stockés les fichiers ?**  
R: Dans `storage/app/attachments/{modeltype}/{id}/`

**Q: Quelle taille max ?**  
R: 10 MB par fichier

**Q: Quels types de fichiers ?**  
R: Images, PDF, Excel, Word, CSV

**Q: Les fichiers sont-ils sécurisés ?**  
R: Oui, stockés en dehors de `public/` et protégés par authentification

**Q: Comment customiser l'interface ?**  
R: Modifiez `resources/views/components/attachments/manager.blade.php`

---

Generated: 2026-04-22
