import{v as d,c as p,w as i,o as c,a,u as o,G as u,b as t,t as l,d as f,n as _,e as w}from"./app-ddfa9cd8.js";import{_ as b}from"./GuestLayout-3537a8f8.js";import{_ as $,a as v,b as g}from"./TextInput-65742199.js";import{P as y}from"./PrimaryButton-683c0c78.js";import"./ApplicationLogo-05e4da24.js";import"./_plugin-vue_export-helper-c27b6911.js";const V={class:"mb-4 text-sm text-gray-600"},h=["onSubmit"],B={class:"flex justify-end mt-4"},q={__name:"ConfirmPassword",setup(k){const s=d({password:""}),n=()=>{s.post(route("password.confirm"),{onFinish:()=>s.reset()})};return(e,r)=>(c(),p(b,null,{default:i(()=>[a(o(u),{title:e.$t("confirm_password")},null,8,["title"]),t("div",V,l(e.$t("confirm_password_description")),1),t("form",{onSubmit:w(n,["prevent"])},[t("div",null,[a($,{for:"password",value:e.$t("password")},null,8,["value"]),a(v,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:o(s).password,"onUpdate:modelValue":r[0]||(r[0]=m=>o(s).password=m),required:"",autocomplete:"current-password",autofocus:""},null,8,["modelValue"]),a(g,{class:"mt-2",message:o(s).errors.password},null,8,["message"])]),t("div",B,[a(y,{class:_(["ml-4",{"opacity-25":o(s).processing}]),disabled:o(s).processing},{default:i(()=>[f(l(e.$t("confirm")),1)]),_:1},8,["class","disabled"])])],40,h)]),_:1}))}};export{q as default};
