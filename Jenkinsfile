pipeline {
    agent any

    tools {
        nodejs "node24.9.0"
    }

    environment {
        SERVER_IP     = "127.0.0.1"
        PROJECT_PATH  = "/home/ai-aesthetics-staging-crm/htdocs/staging-crm.ai-aesthetics.in"
        SSH_KEY       = "/var/lib/jenkins/.ssh/id_ed25519_deploy"
        WEB_USER      = "clp"
        WEB_GROUP     = "clp"
    }

    stages {

        stage('Build Application') {
            steps {
                sh '''
                    composer --version
                    php --version
                    node -v
                    npm -v
                '''

                sh 'npm ci'

                sh '''
                    composer install \
                    --no-interaction \
                    --prefer-dist \
                    --optimize-autoloader --no-dev
                '''

                sh 'npm run build'
            }
        }

        stage('Populate .env File') {
            steps {
                withCredentials([file(credentialsId: 'staging_crm_env', variable: 'ENV_FILE')]) {
                    sh 'cp -f $ENV_FILE .env'
                }
            }
        }

        stage('Deploy Project Files') {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        rsync -avzr --delete \
                        --exclude=".git" \
                        --exclude="node_modules" \
                        --exclude="storage/framework/*" \
                        --exclude="bootstrap/cache/*" \
                        -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" \
                        ./ root@$SERVER_IP:$PROJECT_PATH
                    '''
                }
            }
        }

        stage('Run Server Commands') {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
ssh -i $SSH_KEY -o StrictHostKeyChecking=no root@$SERVER_IP << \'EOF\'

set -e

cd ${PROJECT_PATH}

echo "Current User: $(whoami)"

# Ensure required directories exist
mkdir -p storage/framework/{cache,data,sessions,views,temp} bootstrap/cache
mkdir -p public/storage

# Fix ownership (CloudPanel user)
chown -R ${WEB_USER}:${WEB_GROUP} .

# Set correct permissions
find . -type d -exec chmod 775 {} +
find . -type f -exec chmod 664 {} +
chmod -R 775 storage bootstrap/cache

echo "PHP Version: $(php --version)"

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan migrate --force --no-interaction || true

php artisan shield:generate --panel=admin --all --no-interaction || true

php artisan optimize:clear

php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

php artisan storage:link --force || true

echo "✅ Deployment Finished Successfully"

EOF
                    '''
                }
            }
        }
    }

    post {
        success {
            echo '✅ Deployment completed successfully!'
        }
        failure {
            echo '❌ Deployment failed!'
        }
        always {
            cleanWs()
        }
    }
}
