<template>
    <VfMultiselect
        v-model="locale"
        :options="locales"
        :object="true"
        label="name"
        value-prop="code"
        track-by="code"
        :can-clear="false"
        @change="changeLocale" />
</template>


<script>
    import VfMultiselect from '../elements/VfMultiselect.vue'

    export default {
        name: 'LocaleSwitcher',
        components: {
            VfMultiselect
        },
        data() {
            return {
                locale: null
            }
        },
        computed: {
            locales() {
                return this.$store.getters['getLocales']
            },
            currentLocale() {
                return this.locales.filter(l => {
                    return l.code === this.locale.code
                })[0]
            }
        },
        watch: {
            locale() {
                this.$i18n.locale = this.locale.code
            }
        },
        async beforeMount() {
            await this.$store.dispatch('getLocales')
            this.getDefaultLocale()
        },
        methods: {
            getDefaultLocale() {
                this.locale = this.locales.filter(l => {
                    return l.code === this.$i18n.locale
                })[0]
            },
            changeLocale() {
                this.$emit('on-locale-change', this.currentLocale)
            }
        }
    }
</script>
<style src="@vueform/multiselect/themes/default.css"></style>