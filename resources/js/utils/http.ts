import axios from 'axios';

const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export default http;
