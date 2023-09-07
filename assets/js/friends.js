document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('#query');
    const resultList = document.querySelector('#search-results');
    let timeout = null;

    fetchUsers();

    searchInput.addEventListener('input', function() {
        const query = this.value;
        clearTimeout(timeout);

        timeout = setTimeout(() => {
            fetchUsers(query);
        }, 1500);
    });
});

function fetchUsers(query = '') {
    fetch(`${window.appUserSearchUrl}?query=${query}&limit=9`)
        .then(response => response.json())
        .then(users => {
            displayUsers(users);
        });
}

function displayUsers(users) {
    const resultList = document.querySelector('#search-results');
    if (resultList) {
        resultList.innerHTML = '';

        users.forEach(user => {
            const card = document.createElement('a');
            card.href = `/user/profile/${user.id}`;
            card.className = 'w-full bg-white rounded-lg p-12 flex flex-col justify-center items-center transition-transform transform hover:-translate-y-1 hover:scale-105 shadow-lg hover:shadow-xl';
            card.innerHTML = `
                <div class="mb-8">
                    <img class="object-center object-cover rounded-full h-36 w-36" src="${user.image}" alt="photo">
                </div>
                <div class="text-center">
                    <p class="text-xl text-gray-700 font-bold mb-2">${user.username}</p> 
                    <form class="add-friend-form" data-username="${user.username}">
                        <button class="text-indigo-600 text-sm font-semibold" type="submit">Envoyer une demande d'ami</button>
                    </form>
                </div>
            `;
            resultList.appendChild(card);
        });

        const addFriendForms = document.querySelectorAll('.add-friend-form');
        addFriendForms.forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                const username = form.getAttribute('data-username');
                sendFriendRequest(username);
            });
        });
    }
}

function sendFriendRequest(username) {
    const url = `/user/friends/${username}`;

    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.text())
        .then(message => {
            console.log(message);

            const forms = document.querySelectorAll('.add-friend-form');
            forms.forEach(form => {
                if (form.getAttribute('data-username') === username) {
                    const button = form.querySelector('button');
                    button.textContent = 'Demande envoyée';
                    button.disabled = true;
                }
            });

            const flashMessage = document.querySelector('.flash-message');
            flashMessage.classList.add('show');
            setTimeout(() => {
                flashMessage.classList.remove('show');
            }, 3000);
        })
        .catch(error => {
            console.error('Error sending friend request:', error);
        });
}

$(document).ready(function() {
    $('.addButton').on('click', function() {
        const username = $(this).data('username');
        sendFriendRequest(username);
        $(this).remove();
    });
});
