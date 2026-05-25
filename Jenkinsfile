pipeline {
    agent any

    tools {
        nodejs "node24.9.0"
    }

    environment {
        SERVER_IP   = "127.0.0.1"
        PROJECT_PATH = "/home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in"
        SSH_KEY     = "/var/lib/jenkins/.ssh/id_ed25519_deploy"
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

                sh '''
                    npm ci
                '''

                sh '''
                    composer install \
                    --no-interaction \
                    --prefer-dist \
                    --optimize-autoloader
                '''

                sh '''
                    npm run build
                '''
            }
        }

        stage('Populate .env File') {
            steps {

                withCredentials([
                    file(
                        credentialsId: 'production_crm_env',
                        variable: 'ENV_FILE'
                    )
                ]) {

                    sh '''
                        cp -f $ENV_FILE .env
                    '''
                }
            }
        }

        stage('Verify SSH Connection') {
            steps {

                sshagent(credentials: ['jenkins']) {

                    sh '''
                        ssh \
                        -i $SSH_KEY \
                        -o StrictHostKeyChecking=no \
                        root@$SERVER_IP "
                            whoami
                        "
                    '''
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
                        --exclude="storage/logs/*" \
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
ssh -i $SSH_KEY \
-o StrictHostKeyChecking=no \
root@$SERVER_IP << EOF

set -e

cd $PROJECT_PATH

echo "Current User:"
whoami

echo "PHP Version:"
php --version

echo "Installing Composer Dependencies..."
composer install \
--no-interaction \
--prefer-dist \
--optimize-autoloader

echo "Running Migrations..."
php artisan migrate --force --no-interaction

echo "Generating Shield Permissions..."
php artisan shield:generate --panel=admin --all --no-interaction || true

echo "Clearing Cache..."
php artisan optimize:clear

echo "Caching Config..."
php artisan config:cache

echo "Caching Routes..."
php artisan route:cache || true

echo "Caching Views..."
php artisan view:cache

echo "Creating Storage Link..."
php artisan storage:link || true

echo "Setting Permissions..."

chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache

chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "Deployment Finished Successfully"

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

            cleanWs(
                cleanWhenNotBuilt: false,
                deleteDirs: true,
                disableDeferredWipeout: true,
                notFailBuild: true,
                patterns: [
                    [
                        pattern: '.gitignore',
                        type: 'INCLUDE'
                    ]
                ]
            )
        }
    }
}