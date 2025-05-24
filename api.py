from flask import Flask, request, render_template, jsonify, flash
import tensorflow as tf
import tensorflow_hub as hub
import mysql.connector
import os
import random

import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
import os 

import tensorflow as tf
tf.config.run_functions_eagerly(True)
import tensorflow_hub as hub
from tensorflow.keras.models import Sequential, Model
from tensorflow.keras.layers import Dense, Input, InputLayer
from tensorflow.keras.callbacks import ModelCheckpoint, EarlyStopping


app = Flask(__name__)
app.secret_key = 'supersecretkey'

# Configuration de la base de données
db_config = {
    'host': 'localhost',
    'database': 'classification',
    'user': 'root',
    'password': ''
}

# Fonction pour récupérer les hyperparamètres depuis la base de données
def get_hyperparameters_by_id(hyperparameter_id):
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM parametres WHERE id = %s", (hyperparameter_id,))
    result = cursor.fetchone()
    cursor.close()
    conn.close()
    return result

# Fonction pour récupérer une image par classe
def get_one_image_by_class(directory):
    class_dirs = [os.path.join(directory, d) for d in os.listdir(directory) if os.path.isdir(os.path.join(directory, d))]
    images = {}
    for class_dir in class_dirs:
        class_name = os.path.basename(class_dir)
        image_files = [os.path.join(class_dir, f) for f in os.listdir(class_dir) if os.path.isfile(os.path.join(class_dir, f))]
        if image_files:
            images[class_name] = random.choice(image_files)  # Sélectionner une seule image aléatoire par classe
    return images

@app.route('/train/<int:id>', methods=['GET'])
def train_model(id):
    try:
        flash("Début d'entraînement, attendez quelques minutes...", "info")
        
        # Fetch hyperparameters from the database
        params = get_hyperparameters_by_id(id)
        if not params:
            raise ValueError(f"Aucun hyperparamètre trouvé pour l'ID: {id}")
        
        # Ensure correct types for TensorFlow operations
        params['epochs'] = int(params.get('epochs', 10))  # Default to 10 if not set
        params['batch_size'] = int(params.get('batch_size', 32))  # Default to 32 if not set
        params['patience'] = int(params.get('patience', 5))  # Default to 5 if not set
        params['validation_split'] = float(params.get('validation_split', 0.2))  # Default to 20%
        
        # Train the model
        train_and_evaluate_model(params)
        
        flash("Entraînement terminé avec succès !", "success")
        return jsonify({'status': 'Training completed successfully'})
    
    except Exception as e:
        flash(f"Erreur : {str(e)}", "danger")
        return jsonify({'error': str(e)}), 500


# Fonction pour enregistrer le chemin du modèle dans la base de données
def save_model_path_to_db(model, hyperparameter_id, model_name):
    try:
        # Spécifiez le répertoire où vous souhaitez enregistrer le modèle
        model_dir = "models/"
        if not os.path.exists(model_dir):
            os.makedirs(model_dir)
        
        # Définir le chemin du fichier du modèle
        model_path = os.path.join(model_dir, f"{model_name}.keras")

        # Sauvegarder le modèle sur disque
        model.save(model_path)

        # Connexion à la base de données
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Insérer le chemin du modèle dans la table 'model_paths'
        cursor.execute("""
            INSERT INTO model_paths (hyperparameter_id, model_name, model_path)
            VALUES (%s, %s, %s)
        """, (hyperparameter_id, model_name, model_path))

        conn.commit()
        cursor.close()
        conn.close()

        print(f"Le modèle '{model_name}' a été enregistré avec succès dans la base de données avec le chemin : {model_path}")
    except Exception as e:
        print(f"Erreur lors de l'enregistrement du chemin du modèle : {str(e)}")

# Fonction d'entraînement modifiée pour enregistrer le chemin du modèle après l'entraînement
def train_and_evaluate_model(params):
    # Extraire les paramètres
    epochs = int(params['epochs'])
    batch_size = int(params['batch_size'])
    patience = int(params['patience'])
    monitor = params['Monitor']
    optimizer = params['optimizer']
    activation_function = params['activation_function'].lower()
    validation_split = float(params['validation_split'])
    directory = params['directory']

    # Préparation du dataset
    train = tf.keras.utils.image_dataset_from_directory(
        directory,
        validation_split=validation_split,
        subset="training",
        seed=123,
        image_size=(224, 224),
        batch_size=batch_size,
        label_mode="categorical"
    )

    val = tf.keras.utils.image_dataset_from_directory(
        directory,
        validation_split=validation_split,
        subset="validation",
        seed=123,
        image_size=(224, 224),
        batch_size=batch_size,
        label_mode="categorical"
    )

    # Définir le modèle
    model_url = 'https://tfhub.dev/google/imagenet/resnet_v2_50/feature_vector/5'
    model = tf.keras.Sequential([
        tf.keras.layers.Rescaling(1./255, input_shape=(224, 224, 3)),
        hub.KerasLayer(model_url),
        tf.keras.layers.Dense(10, activation="softmax")
    ])

    # Choisir l'optimiseur
    if optimizer == 'adam':
        optimizer_instance = tf.keras.optimizers.Adam()
    else:
        optimizer_instance = tf.keras.optimizers.SGD()

    model.compile(
        loss=tf.keras.losses.CategoricalCrossentropy(),
        optimizer=optimizer_instance,
        metrics=["accuracy"]
    )

    # Entraîner le modèle
    model.fit(
        train,
        epochs=epochs,
        validation_data=val,
        callbacks=[
            tf.keras.callbacks.EarlyStopping(monitor='val_loss', patience=patience, restore_best_weights=True),
            tf.keras.callbacks.ModelCheckpoint('best_model.keras', save_best_only=True)
        ]
    )

    # Enregistrer le chemin du modèle dans la base de données
    save_model_path_to_db(model, params['id'], "resnet_model_{}".format(params['id']))

def load_model_from_db(model_id):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Récupérer le chemin du modèle depuis la base de données
        cursor.execute("SELECT model_file_path FROM model_paths WHERE id = %s", (model_id,))
        result = cursor.fetchone()

        if result:
            model_path = result[0]
            # Charger le modèle depuis le chemin récupéré
            model = tf.keras.models.load_model(model_path)
            return model
        else:
            print("Modèle non trouvé.")
            return None
    except Exception as e:
        print(f"Erreur lors du chargement du modèle : {str(e)}")
        return None


@app.route('/resultat/<int:id>', methods=['GET'])
def resultat(id):
    # # Utiliser les deux endpoints pour obtenir les résultats et les images
    # train_result = train_model(id).json

    return render_template('resultat.html', images=images_result)

if __name__ == '__main__':
    app.run(debug=True)