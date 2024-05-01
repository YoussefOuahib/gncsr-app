import axios from "axios";
import {  ref, reactive } from "vue";
import { useRouter } from "vue-router";

export default function useCustomers() {
    const customers = ref([]);

    const getCustomers = () => {
        axios.get("/api/customers").then(response => {
            customers.value = response.data.customers;
        }).catch(error => {
            console.log(error);
        })
    }
    const updateConnectionCredentials = async (credentialsForm, userId) => {
        axios.put("api/update/credentials/" + userId , credentialsForm
        ).catch((error) => { console.log(error) });
    }
    const updateCustomerCredentials = async (customerForm, userId) => {
        axios.put("/api/customers/" + userId, customerForm
        ).catch((error) => { console.log(error) });
    }
    const addNewCustomer = async(customerForm) => {
        console.log('hello add new customer');
        axios.post('/api/customers', customerForm).catch((error) => {
            console.log(error);
        })
    }
    const deleteCustomer = async(customerId) => {
        axios.delete('/api/customers').catch((error) => {
            console.log(error);
        })
    }
    return {
        getCustomers,
        customers,
        updateConnectionCredentials,
        deleteCustomer,
        addNewCustomer,
        updateCustomerCredentials,
    }
}