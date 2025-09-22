node {
    stage('Checkout Code') {
        git url: 'https://github.com/kmsuman2705/Testing.git', branch: 'main'
    }

    stage('Deploy to EC2-A') {
        sshagent(credentials: ['sumancricket']) {
            sh """
                ssh -o StrictHostKeyChecking=no ubuntu@13.201.93.186 '
                    sudo rm -rf /var/www/html
                    sudo git clone https://github.com/kmsuman2705/Testing.git /var/www/html
                    sudo chown -R www-data:www-data /var/www/html
                '
            """
        }
    }
}

